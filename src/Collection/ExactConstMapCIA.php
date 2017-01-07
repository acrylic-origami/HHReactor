<?hh // strict
namespace HHRx\Collection;
use HHRx\Collection\MapIA;
use HHRx\Collection\ConstMapCIA;
use HHRx\Collection\ConstVectorCIA;
use HHRx\Collection\ConstVectorKeys;
// I'm so sorry.
type ExactConstMapCIA<Tk, +Tv> = ConstMapCIA<Tk, Tv, \ConstMap<Tk, Tv>, \ConstVector<Tk>, ConstVectorKeys, ConstVectorCIA<Tk, \ConstVector<Tk>, ConstVectorKeys>>;