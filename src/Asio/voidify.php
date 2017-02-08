<?hh // strict
namespace HHReactor\Asio;
function voidify(Awaitable<mixed> $A): WaitHandle<void> {
	$voided = async {
		await $A;
	};
	return $voided->getWaitHandle();
}