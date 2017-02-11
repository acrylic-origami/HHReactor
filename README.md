# HHReactor

HHReactor implements ReactiveX operators in **pure strict Hack**, using solely `Awaitable`, `AsyncIterator` and wait handles to orchestrate its asynchronous producers. This project has [a complementary `Observable` library in true ReactiveX style](https://github.com/acrylic-origami/HHRx) called HHRx. That library, however, will come to rely on this one, as they are functionally equivalent but this, well, _actually_ cooperates with Hack's built-in cooperative multitasking features, notably `await`, `break` and `try`-`catch`.

## Usage

### By example

```hack
<?hh // partial
require_once(__DIR__ . '/../vendor/hh_autoload.php');
async function f<T>(T $v): AsyncIterator<T> {
	await HH\Asio\later();
	yield $v;
}
$producers = Vector{ $factory->make(f(1)), $factory->make(f(2)) };
$river = Producer::merge($producers);

\HH\Asio\join(\HH\Asio\v((Vector{
	async {
		foreach($river await as $v)
			printf("River: %d\n", $v);
		echo "River: END\n";
	}
})->concat($producers->mapWithKey(async ($k, $producer) ==> {
	foreach($producer await as $v)
		printf("Producer %d: %d\n", $k, $v);
	printf("Producer %d: END", $k);
}))));
```

### By component

#### Collection\Producer<T>

`Producer` as a separate, fully-fledged class arose out of the backpressure problem. When multiple `Producers` are bound to a single `AsyncIterator[Wrapper]`, each maintains a list &mdash; a `LinkedList` to be precise &mdash; of the values that have been added since the last that that `Producer` emitted values.

`Producer` is now the workhorse of `HHReactor`, and will soon take on the same role backstage in `HHRx`. This is owing [I'd say entirely] to its being an `AsyncIterator`. Using a `Producer` over callbacks makes it very clear what parts of the application are and need to be `async`. Note that the example above is already shorter -- it would be yet more so if error handling (`try`-`catch`) and premature termination (`break`, `return`) were included.

To avoid a memory leak present in the naive implementaion (e.g. implementing the lagging list with a `Vector`), the HHReactor `LinkedList` implementation marches its head to shed references to lagged nodes that have already been emitted. See `LinkedList::shift`.

**ReactiveX operators**

- **Instance methods**
	- _Standard_
		- [`map<Tv>(T -> Tv): Producer<Tv>`](http://reactivex.io/documentation/operators/map.html)
		- [`scan((T, T) -> T): this`](http://reactivex.io/documentation/operators/scan.html)
		- [`last(): ?T`](http://reactivex.io/documentation/operators/last.html)
		- [`reduce((T, T) -> T): this`](http://reactivex.io/documentation/operators/reduce.html)
		- [`flat_map<Tv>((T) -> Producer<Tv>): Producer<Tv>`](http://reactivex.io/documentation/operators/flatmap.html)
		- [`group_by<Tk <: arraykey>(T -> Tk): Producer<this>`](http://reactivex.io/documentation/operators/groupby.html)
		- [`buffer(Producer<mixed>): Producer<ConstVector<T>>`](http://reactivex.io/documentation/operators/buffer.html)
		- [`window(Producer<mixed>): Producer<this>`](http://reactivex.io/documentation/operators/window.html)
	- _Custom_
		- `collapse(): Awaitable<ConstVector<T>>`: flatten the producer over time and return a `Vector` of all the values produced
- Static methods
	- _Standard_
		- [`just(T): this`](http://reactivex.io/documentation/operators/just.html)
		- [`from(Iterable<Awaitable<T>>): this`](http://reactivex.io/documentation/operators/from.html)
		- [`merge(Traversable<this>): this`](http://reactivex.io/documentation/operators/merge.html)
		- [`defer(() -> Producer<T>): this`](http://reactivex.io/documentation/operators/defer.html)
		- [`empty(): this`](http://reactivex.io/documentation/operators/empty.html)
		- [`throw(Exception): this`](http://reactivex.io/documentation/operators/throw.html)
		- [`interval(int): Producer<int>`](http://reactivex.io/documentation/operators/interval.html)
		- [`range(int, int): Producer<int>`](http://reactivex.io/documentation/operators/range.html)
		- [`repeat(T, ?int): this`](http://reactivex.io/documentation/operators/repeat.html)
		- [`timer(T, int): this`](http://reactivex.io/documentation/operators/timer.html)
	- _Custom_
		- `repeat_sequence(Traversable<T>, ?int): this`: Variant of `repeat` that cycles through the `Traversable` some number of times, or infinitely.
		- `from_nonblocking(Iterable<Awaitable<T>>): this`: Variant of `from`, where the collection of `Awaitable`s don't block each other in a loop -- they all kick off simultaneously at the time the function is called.

**Details about `merge`**

The most non-trivial operation by far is `merge`. The most basic operation of a merge is to emit the value that is first to resolve from a collection of `Awaitable`s. This follows directly from iteratively observing the collection of `Iterator`s for the first `next` to resolve; doing this over and over to produce the merged stream.

I am eternally grateful for [@jano sharing his `AsyncPoll` implementation](https://github.com/hhvm/asio-utilities/pull/11). I elaborate on the fundamental operation in [this SO answer](http://stackoverflow.com/a/41406845/3925507). The gist is that there exists a wait handle called `ConditionWaitHandle` that wraps an upper-bounding wait handle, that crucially can be _notified by any scope_ to resolve to a certain value. This allows for a collection of `Awaitable`s to race, which enables the `merge` operation naturally.

The implementation in HHReactor is more minimal, mostly by merit of closures, which preserve references to the "race handle". I found the generator function to be a much cleaner home for `AsyncPoll` functionality over a class, especially when backpressure enters the picture (cf. `Producer::fast_forward` and `AsyncPoll::producer` implementation).

**A caveat for those interested in the `ConditionWaitHandle` usage in HHReactor**: the notifiers `succeed` and `fail` _do not_ immedately transfer control to the scopes `await`ing the `ConditionWaitHandle`. Instead, these wait handles are resolved internally, and pushed into the queue of ready wait handles to be processed when `HH\Asio\join()` is hit again. To force this to happen immediately, the notifying scope awaits `HH\Asio\later()` right after notifying.

#### Asio\ResettableConditionWaitHandle<T>

As hinted at before, as much of a godsend as `ConditionWaitHandle` is, it requires some massaging to better fit the use cases in the application. `ResettableConditionWaitHandle` helps with one, namely that, because there is no easy way to an "infinite-lifetime" Awaitable (that never regains control, e.g. not `while(true) await \HH\Asio\later();`), this provides the null-checking support for setting a `ConditionWaitHandle` with the lifetime of a child. Also convenient: notifications automatically 'reset' the internal `ConditionWaitHandle` from an `Awaitable` from a factory. This and `Producer::_listen_produce` cooperate closely to provide the iterative behavior for a lot of the operators.

#### Collection\AsyncIteratorWrapper<T>

An HHVM-specific wrinkle is that `AsyncGenerator`, which comes from `async` methods that `yield` values, cannot have `next` called on it multiple times while it is still resolving, and will err with "Generator already running". Note `AsyncGenerator <: AsyncIterator`. `AsyncIteratorWrapper` multicasts `AsyncGenerator::next` by maintaining its own handle on `next` and sharing it with handlers.

## How it works

### Some nuances about the Hack await-async implementation

Await-async is a [cooperative-multitasking model](http://hhvm.com/blog/7091/async-cooperative-multitasking-for-hack). As a result, synchronous code running between `await` statements _cannot_ be interrupted by `Awaitable`s resolving. Only when control returns to a `join` call is the queue of resolved `Awaitable`s processed.

Some `Awaitable`s that complete right away do not yield control to the calling scope when `await`ed. For example, there is no ambiguity to the order the following prints:

```hack
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

These are known as "ready wait-handles" &mdash; prior to `await`ing, `HH\Asio\has_finished` would report that they are already finished.

However, when multiple non-ready-wait handles resolve before control returns to `join`, it is a more interesting picture. The order that they are processed is [undefined by specification](http://stackoverflow.com/a/41650153/3925507). Since there is a large bulk of the codebase that relies on asynchronous resetting that mustn't be interrupted, enforcing some order becomes paramount.

Awaiting `HH\Asio\later()` defers resolution until at least the next time `HH\Asio\join` is hit. More formally, it spawns an `Awaitable` that has the lowest priority in the default or IO scheduler, depending on which is specified. As a result, it is used mostly to return control as quickly as possible to the `join` within `async` methods (e.g. `ResettableConditionWaitHandle::_notify`, and to transform ready-wait handles to pending handles in `async` blocks (e.g. `AsyncPoll::producer`).

_[This section is incomplete. Watch for more details!]_