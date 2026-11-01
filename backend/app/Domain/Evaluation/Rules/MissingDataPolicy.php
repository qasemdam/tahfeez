<?php

namespace App\Domain\Evaluation\Rules;

/**
 * What the engine does when a criterion has no data (BR-EVL-12, OD-009).
 */
enum MissingDataPolicy: string
{
    case TreatAsZero = 'treat_as_zero';

    case ExcludeAndRenormalize = 'exclude_and_renormalize';

    case Block = 'block';
}
