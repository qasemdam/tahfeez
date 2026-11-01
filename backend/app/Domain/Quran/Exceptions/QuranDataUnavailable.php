<?php

namespace App\Domain\Quran\Exceptions;

use RuntimeException;

/**
 * The active Quran dataset cannot answer the question (missing page, surah or
 * ayah, or no dataset at all). The caller must fail clearly, never guess.
 */
class QuranDataUnavailable extends RuntimeException {}
