<?php

namespace App\Logging;

use Psr\Log\LoggerInterface;

class NativeLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        error_log('[EMERGENCY] ' . $this->format($message, $context));
    }

    public function alert($message, array $context = []): void
    {
        error_log('[ALERT] ' . $this->format($message, $context));
    }

    public function critical($message, array $context = []): void
    {
        error_log('[CRITICAL] ' . $this->format($message, $context));
    }

    public function error($message, array $context = []): void
    {
        error_log('[ERROR] ' . $this->format($message, $context));
    }

    public function warning($message, array $context = []): void
    {
        error_log('[WARNING] ' . $this->format($message, $context));
    }

    public function notice($message, array $context = []): void
    {
        error_log('[NOTICE] ' . $this->format($message, $context));
    }

    public function info($message, array $context = []): void
    {
        error_log('[INFO] ' . $this->format($message, $context));
    }

    public function debug($message, array $context = []): void
    {
        error_log('[DEBUG] ' . $this->format($message, $context));
    }

    public function log($level, $message, array $context = []): void
    {
        error_log('[' . strtoupper($level) . '] ' . $this->format($message, $context));
    }

    private function format($message, array $context): string
    {
        return $message . (!empty($context) ? ' ' . json_encode($context) : '');
    }
}
