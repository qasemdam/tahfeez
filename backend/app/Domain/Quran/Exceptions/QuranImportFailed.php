<?php

namespace App\Domain\Quran\Exceptions;

use RuntimeException;

/**
 * A dataset could not be read, validated or stored.
 *
 * The message is written for the operator running the import, not for an API
 * client: it names the rule that failed and the row that broke it.
 */
class QuranImportFailed extends RuntimeException {}
