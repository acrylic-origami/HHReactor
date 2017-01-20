# HHRx

HHRx implements Reactive extensions in **pure strict Hack**, using solely `Awaitable` and wait handles to orchestrate concurrent streams and manage the lifetime of the application.

(Jan 19, 2017) Development has been intently focussed on the most non-trivial operators, notably `merge` and backpressure operators like `buffer` and `until`, because the needs of their implementations speak the loudest to the implementation of the core. HHRx does not yet conform to [the Observable Contract](http://reactivex.io/documentation/contract.html), most glaringly as proper error handling is not yet implemented. It is a straightforward fix, but yields precedence to stability of streams.

## Usage

### By example

```php
<?hh // partial
use HHRx\StreamFactory;
$factory = new StreamFactory();
$delays = Vector{ 300, 400, 1000 }; // delays in ms
$streams = $delays->map((int $delay) ==> $factory->tick($delay * 1000)) // convert to us
                  ->map((int $delay) ==> sprintf('Waited %d us', $delay)); // transform emitted values

// NOTE the factory performing the merge
$river = $factory->merge($streams);
// ... but the stream performing `end_on`,
// since it mutates the stream.
$river->end_on(\HH\Asio\usleep(10 * 1000000)); // 10s

// NOTE the async handler, which is that 
// way for generality (and for some 
// internal behavior).
$river->subscribe(
	async (string $delay_msg) ==> var_dump($delay_msg)
);

// Kick off the application
HH\Asio\join($factory->get_total_awaitable());
```

### By component

I will use the term "end-safe" to describe some `Awaitable`s. These are **guaranteed** to resolve before the application ends. The reason is core to the implementation, and is explained in the **How it works** section.

#### Stream<T>

`Stream` generates values over time, broadcasting them to subscribers. _If_* they end, they broadcast values to end subscribers.

At their core, they wrap `AsyncIterator`s, which do much of the heavy lifting of producing values over time. The responsibility of `Stream` by itself is managing subscribers and providing the necessary plumbing to complete before their underlying `AsyncIterator`s.

**They are always hot by construction.** I am philosophically opposed to the notion of cold streams by the unnecessary and dangerous amibiguity they present. Note that in HHVM, `Iterator`s can be cloned, and the way `Stream` wraps `Iterator` affords maximum flexibility to developers to manage "cold" behavior explicitly.

* Standard Rx operators:
	* [`::map<Tv>((function(T):Tv)): Stream<Tv>`](http://reactivex.io/documentation/operators/map.html)
	* [`::buffer(Stream<mixed>): Stream<\ConstVector<T>>`](http://reactivex.io/documentation/operators/buffer.html). Note that this `buffer` implementation uses a stream for ticking vs. a time interval.
	* [`::using(Stream<mixed>): void`](http://reactivex.io/documentation/operators/using.html): bounds the stream to the end of another, shorter-lived stream.
* Custom utility methods:
	* `::await_end(): Awaitable<void>`: provides an end-safe `Awaitable` that resolves after the stream errs or ends.
	* `::collapse(): Awaitable<\ConstVector<T>>`: buffers the whole stream. End-safe.
	* `::end_on(Awaitable<mixed>): void`: bounds the stream to a shorter-lived `Awaitable`. Can be called multiple times.
	* `::clone_producer(): Producer<T>`: crucial for internal operations, **not end-safe if awaited** &mdash; use `::await_end` for that purpose. **How it works** section for the curious.

<sup>* Eternal streams in the HHRx environment are not impossible, but are proving to be rather unnatural. They are elaborated in the **How it works** section.</sup>

#### StreamFactory

`StreamFactory` takes such precedence in the usage guide because through its construction of streams, and it alone, it maintains the longest-running `Awaitable` in the application that subsequently _is_ the lifetime of the application. Therefore, all operators either act in conjunction with or are implemented by an instance of the factory.

* The factory operation is `StreamFactory::make<T>(AsyncIterator<T>): Stream<T>`.
	* A separate factory operation, `::bounded_make(AsyncIterator<T>): Stream<T>` is provided to make a stream bounded by the application lifetime out of a long- or infinite-running `AsyncIterator`.
* Standard Rx operators:
	* [`::merge<T>(Iterable<Stream<T>>): Stream<T>`](http://reactivex.io/documentation/operators/merge.html)
	* [`::concat<T>(Iterable<Awaitable<T>>): Stream<T>`](http://reactivex.io/documentation/operators/concat.html)
	* [`::just<T>(Awaitable<T>): Stream<T>`](http://reactivex.io/documentation/operators/just.html)
	* [`::from<T>(Iterable<Awaitable<T>>): Stream<T>`](http://reactivex.io/documentation/operators/from.html)

#### Streamlined<T>

`Streamlined` is an interface for `Stream` wrappers that provide functionality outside of the `Stream` they wrap. It exposes this undercurrent with `get_local_stream`.

An example would be a reactive database (you bet this is coming soon!), that maintains a read data stream, alongside any number of utility functions we'd expect.

## How it works

### Some nuances about the Hack await-async implementation

Await-async is a [cooperative-multitasking model](http://hhvm.com/blog/7091/async-cooperative-multitasking-for-hack). As a result, synchronous code running between `await` statements _cannot_ be interrupted by `Awaitable`s resolving. Only when control returns to a `join` call is the queue of resolved `Awaitable`s processed.

Some `Awaitable`s that complete right away do not yield control to the calling scope when `await`ed. For example, there is no ambiguity to the order the following prints:

```php
<?hh // partial
HH\Asio\join(HH\Asio\v(Vector{
	async {
		await async{}; // it's as if this statement doesn't exist
		echo 'FIRST';
	},
	async {
		await \HH\Asio\usleep(1);
		echo 'SECOND';
	}
}));
```

These are known as "ready wait-handles" &mdash; prior to `await`ing, `HH\Asio\is_finished` would report that they are already finished.

However, when multiple non-ready-wait handles resolve before control returns to `join`, it is a more interesting picture. The order that they are processed is [undefined by specification](http://stackoverflow.com/a/41650153/3925507). Since there is a large bulk of the codebase that relies on asynchronous resetting that mustn't be interrupted, enforcing some order becomes paramount.

Awaiting `HH\Asio\later()` defers resolution until at least the next time `HH\Asio\join` is hit. More formally, it spawns an `Awaitable` that has the lowest priority in the default or IO scheduler, depending on which is specified. `HH\Asio\later()` is everywhere in the HHRx codebase: it alone provides the ordering where it is crucial.

[Tip: read the `TotalAwaitable` documentation first.] The other crucial consequence is that any `Awaitable` that is not added to the `TotalAwaitable` _might not resolve_, even if it depends on the exact same `Awaitable`s as the application `TotalAwaitable`. This is because, if both are queued in the scheduler and the `TotalAwaitable` resolves first, the application exits before this `Awaitable` resolves. This is why some `Awaitable`s are end-safe, and some aren't.

_[This section is incomplete. Watch for more details!]_

### Helper classes

#### AsyncPoll

The most non-trivial operation by far is `merge`. The most basic operation of a merge is to emit the value that is first to resolve from a collection of `Awaitable`s. This follows directly from iteratively observing the collection of `Iterator`s for the first `next` to resolve; doing this over and over to produce the merged stream.

I am eternally grateful for [@jano sharing his `AsyncPoll` implementation](https://github.com/hhvm/asio-utilities/pull/11). I elaborate on the fundamental operation in [this SO answer](http://stackoverflow.com/a/41406845/3925507). The gist is that there exists a wait handle called `ConditionWaitHandle` that wraps an upper-bounding wait handle, that crucially can be _notified by any scope_ to resolve to a certain value. This allows for a collection of `Awaitable`s to race, which enables the `merge` operation naturally.

The implementation in HHRx is more minimal, mostly by merit of closures, which preserve references to the "race handle". I found the generator function to be a much cleaner home for `AsyncPoll` functionality over a class, especially when backpressure enters the picture (cf. `Producer::fast_forward` and `AsyncPoll::producer` implementation).

**A caveat for those interested in the `ConditionWaitHandle` usage in HHRx**: the notifiers `succeed` and `fail` _do not_ immedately transfer control to the scopes `await`ing the `ConditionWaitHandle`. Instead, these wait handles are resolved internally, and pushed into the queue of ready wait handles to be processed when `HH\Asio\join()` is hit again. To force this to happen immediately, the notifying scope awaits `HH\Asio\later()` right after notifying.

#### TotalAwaitable

At any instant during the application lifetime, an asynchronous object, be it a stream or `Awaitable`, could be created with a lifetime past the immediate lifetime of the application. `TotalAwaitable` allows the application lifetime to be extended dynamically and immediately when these objects are created. In fact, _this is the object that `join`ed at the top-level to yield the application lifetime_. As a result, it must also ensure that these asynchronous objects are kicked off as soon as they are added to avoid unintentional and unwanted dependencies.

#### Collection\AsyncIteratorWrapper<T>

An HHVM-specific wrinkle is that `AsyncGenerator`, which comes from `async` methods that `yield` values, cannot have `next` called on it multiple times while it is still resolving, and will err with "Generator already running". Note `AsyncGenerator <: AsyncIterator`. `AsyncIteratorWrapper` multicasts `AsyncGenerator::next` by maintaining its own handle on `next` and sharing it with handlers.

#### Collection\Producer<T>

`Producer` as a separate, fully-fledged class arose out of the backpressure problem. When multiple `Producers` are bound to a single `AsyncIterator[Wrapper]`, each maintains a list &mdash; a `LinkedList` to be precise &mdash; of the values that have been added since the last that that `Producer` emitted values.

To avoid a memory leak present in the naive implementaion (e.g. implementing the lagging list with a `Vector`), the HHRx `LinkedList` implementation marches its head to shed references to lagged nodes that have already been emitted. See `LinkedList::shift`.

#### Collection\Haltable<T> & Collection\IHaltable<T>

Many Rx operators involve bounding streams to future events. The expectation is that, the moment that future event occurs, the bounded stream will end. The most natural implementation, however, is for the last element the stream is `await`ing to finish before the stream ends, which is markedly different behavior. To stop a stream in its tracks immediately, `Haltable` is used, which allows any scope to notify an exception (for `Iterator` or elsewhere) or `null` (for `AsyncIterator`) instantly to the waiting scopes, usually `Producer`s.

`ConditionWaitHandle` forms the foundation of the implementation.