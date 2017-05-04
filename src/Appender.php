<?hh // strict
namespace HHReactor;
type Appender<T> = (function(AsyncIterator<T>): void);