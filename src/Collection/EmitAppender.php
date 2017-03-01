<?hh // strict
namespace HHReactor\Collection;
type EmitAppender<-T> = (function(AsyncIterator<T>): void);