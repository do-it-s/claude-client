<?php

namespace DoITs\ClaudeClient\Exceptions;

/**
 * The request itself failed (connection error, timeout, ...), as opposed to
 * the API answering with an error status.
 */
class TransportException extends ClaudeClientException {}
