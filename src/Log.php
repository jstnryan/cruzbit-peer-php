<?php

namespace cruzbit;

//TODO: create interface
class Log {

    protected $threshold;

    public function __construct($threshold) {
        $this->threshold = $threshold;
    }

    public function write($level, $message) {
        if ($this->threshold > 0 && $level <= $this->threshold) {
            echo $message . "\n";
        }
        if ($level === 0) {
            //log all critical errors
            error_log($message);
        }
    }

}
