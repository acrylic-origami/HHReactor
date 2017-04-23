## HHReactor

HHReactor implements the ReactiveX operators that you know and love, but embraces the existing async features of Hack, and defects from the Reactive Manifesto with an I-call-you iterator-based mechanic over the you-call-me callback-based approach. There are no objects managing subscriptions, and there is no need: `Producer`s generate elements with `yield`, these elements are retrieved through `foreach-await`, errors are handled with `try-catch`, and completion logic is whatever follows the `foreach` block. The entire library behavior is concentrated in `Producer`, which provides all of the operators.

Single-valued operators &mdash; aggregate operators like `reduce` or filters like `last` &mdash; also have the convenience and expressive power of being `Awaitable`s over `AsyncIterator`s, so their values are usable immediately in proceeding code.

### Usage

First, a PSA: **clone `Producer`s before iterating.** That is, `Producer`s must be one-to-one with consumers.

`Producer`s wrap `AsyncIterator`s at their core. These iterators may be out of sync with the consumers of a `Producer` by any number of elements, so each instance of `Producer` tracks its lag and catches its consumer up when it regains control. However, note that the lag is a single queue, and if many consumers try to consume a single instance of `Producer`, they race against each other to consume the lag. Multicasting a `Producer` also damages the timing of the `Producer` because of the way it is implemented (it informs its consumers lazily). These race conditions can end a `Producer` prematurely.

<sup>Nasty bugs during development have come from operators not cloning the incoming producers. If you see something along the lines of `foreach($this await as ...)` over `foreach(clone $this await as ...)`, please kindly report it in an issue.</sup>

#### Example

The example below also shows off `count_up()` &mdash; an infinite generator of ints &mdash; and `collapse()` &mdash; which transforms the `Producer` to an `Awaitable`.

```hack
<?hh
use \HHReactor\Collection\Producer;
$root = Producer::create(async {
	for($i = 0; $i < 10; $i++) {
		yield $i;
		if(round(rand()/getrandmax()))
			await \HH\Asio\later(); // every now and then, do some work
	}
});

// Identify values > 4
$gt_4 = (clone $root)->group_by((int $v) ==> intval($v > 4)); // bifurcate to two Producers by their >4-ness
$gt_4 = Producer::zip($gt_4, Producer::count_up(), 
	(Producer<int> $A, int $B) ==> $A->map((int $v) ==> sprintf('Is %d >4? %s', $v, $B ? 'Yes' : 'No'))) // identify the producers by their >4-ness
            ->flat_map(($I) ==> $I); // collapse back to one producer

// Get the last even value
$last_odd = (clone $root)->filter((int $v) ==> (bool) $v % 2)
                         ->last();
\HH\Asio\join(async {
	$all_gt_4 = await $gt_4->collapse(); // collapse to Vector of all generated items
	var_dump($all_gt_4);
	
	$last_odd = await $last_odd; // just await the single value
	var_dump($last_odd);
});
```

### Resource management

`Producer`s aim to be as lazy as possible to setup resources and as proactive as it can with the passive messages it receives to tear resources down.

#### Setup

Hack doesn't guarantee any behavior when `Awaitable`s are not `await`ed upon creation, but the spec suggests that when the underlying system calls block, then `await`s will begin to block their scopes. This matters with user-land `AsyncIterator`s, but not with `AsyncGenerator`s, which aren't started at all until the first `next` call to them, which `Producer` defers until its own first `next` call.

#### Teardown

When the last running (i.e. at least one element has been produced) `Producer` reference becomes inaccessible, the `Producer` will stop iterating its children and, as a result, stops buffering elements. When all references (both running and unstarted) to a `Producer` become inaccessible, and it holds the only references to its children (it should), then HHReactor leaves it to the GC to clean up the resources. If the need arises, descendants of `Producer` may define a destructor that cleans up resources explicitly too.