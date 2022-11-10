<?php

namespace Nacho\Hooks;

use Exception;

abstract class AbstractAnchor
{
    protected array $hooks = [];
    protected array $arguments = [];
    
    public function addHook($hook)
    {
        $this->hooks[] = $hook;
    }

    public function run(array $args = [])
    {
        $this->populateArguments($args);
        foreach ($this->hooks as $hook) {
            $cls = new $hook();
            $this->exec($cls);
        }

        if ($this->getIsReturnVar() !== null) {
            return $this->arguments[$this->getIsReturnVar()]->getValue();
        }
    }

    public function exec(mixed $hook): void
    {
        throw new Exception('This anchor does\'t have it\'s exec function defined');
    }

    private function populateArguments(array $args)
    {
        foreach ($this->arguments as $i => $argument) {
            if (key_exists($argument->getName(), $args)) {
                $this->arguments[$i]->setValue($args[$argument->getName()]);
            }
        }
    }

    protected function getIsReturnVar(): ?int
    {
        foreach ($this->arguments as $i => $argument) {
            if ($argument->getIsRet()) {
                return $i;
            }
        }

        return null;
    }
}