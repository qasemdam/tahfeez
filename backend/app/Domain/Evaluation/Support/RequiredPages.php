<?php

namespace App\Domain\Evaluation\Support;

/**
 * The documented baseline requirement (BR-EVL-01): one page per week.
 *
 * This is the single place the default lives. It applies only when a student
 * has no evaluation profile version in force — never as an override of a
 * configured requirement.
 */
final class RequiredPages
{
    public const DEFAULT = '1.00';
}
