<?hh // strict
namespace HHRx;
class AsyncPoll<+T> extends AsyncKeyedPoll<mixed, T> implements AsyncIterator<T> {}