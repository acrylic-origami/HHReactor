<?hh // strict
namespace HHReactor\Asio;
class ConstDependencies<+T> {
	/* HH_FIXME[4120] Waiting for object-protected */
	protected Map<string, Awaitable<T>> $dependencies = Map{};
	private Status $any = shape('succeeded' => false, 'failed' => false, 'finished' => false);
	protected Status $all = shape('succeeded' => false, 'failed' => false, 'finished' => false);
	// note that `any` state is idempotent, but `all` is subject to change with future `depend`.
	
	public function get_dependencies(): \ConstMap<string, Awaitable<T>> {
		return $this->dependencies;
	}
	
	public function failed(): Vector<\Exception> {
		$exceptions = Vector{};
		foreach($this->dependencies as $dependency) {
			if($dependency->getWaitHandle()->isFailed()) {
				try {
					$dependency->getWaitHandle()->result();
				}
				catch(\Exception $e) {
					$exceptions->add($e);
				}
			}
		}
		return $exceptions;
	}
	
	public function any_finished(): bool {
		return ($this->any['finished'] = $this->any_failed() || $this->any_succeeded());
	}
	
	public function all_finished(): bool {
		if(!$this->all['finished'])
			foreach($this->dependencies as $dependency)
				if(!$dependency->getWaitHandle()->isFinished())
					return ($this->all['finished'] = false);
		return ($this->all['finished'] = true);
	}
	
	public function any_failed(): bool {
		if(!$this->any['failed']) {
			foreach($this->dependencies as $dependency)
				if(!$dependency->getWaitHandle()->isFailed())
					return ($this->any['failed'] = true);
			return false;
		}
		else
			return true;
	}
	
	public function all_failed(): bool {
		if(!$this->all['failed'])
			foreach($this->dependencies as $dependency)
				if(!$dependency->getWaitHandle()->isFailed())
					return ($this->all['failed'] = false);
		return ($this->all['failed'] = true);
	}
	
	public function any_succeeded(): bool {
		if(!$this->any['succeeded']) {
			foreach($this->dependencies as $dependency)
				if($dependency->getWaitHandle()->isSucceeded())
					return ($this->any['succeeded'] = true);
			return false;
		}
		else
			return true;
	}
	
	public function all_succeeded(): bool {
		if(!$this->all['succeeded'])
			foreach($this->dependencies as $dependency)
				if(!$dependency->getWaitHandle()->isSucceeded())
					return ($this->all['succeeded'] = false);
		return ($this->all['succeeded'] = true);
	}
}