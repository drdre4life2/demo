<?php
// api/src/Enum/PromotionStatus.php
declare(strict_types=1);

namespace App\Enum;
/**
 * A list of possible promotion statuses for the item.
 *
 * @see https://schema.org/OfferItemCondition
 */

enum PromotionStatus: string
{
    case None = 'None';
    case Basic = 'Basic';
    case Pro = 'Pro';
}
