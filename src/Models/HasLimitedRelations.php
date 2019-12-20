<?php


namespace OnlineVerkaufen\Subscriptions\Models;


use OnlineVerkaufen\Subscriptions\Exception\FeatureException;
use stdClass;

trait HasLimitedRelations
{
    /**
     * @param string $relation
     * @return stdClass
     * @throws FeatureException
     */
    public function getUsageFor(string $relation): stdClass
    {
        if ($this->$relation) {
            $usedItems = count($this->$relation()->get());
            $availableItems = $this->getFeatureAvailabilityForRelation($relation);
            $remainingItems = $availableItems - $usedItems;

            return (object)[
                'used' => $usedItems,
                'available' => $availableItems,
                'remaining' => $remainingItems
            ];
        }
        throw new FeatureException(sprintf('restricted relation %s not found on model %s', $relation, get_class($this)));
    }

    /** @throws FeatureException */
    public function getUsages(): ?array
    {
        $restrictedRelations = $this->getRestrictedRelations();
        if (!is_array($restrictedRelations) || count($restrictedRelations) === 0) {
            return null;
        }

        $usagesArray = [];

        foreach ($restrictedRelations as $restrictedRelation) {
            $usagesArray[$restrictedRelation] = $this->getUsageFor($restrictedRelation);
        }

        return $usagesArray;
    }

    private function getFeatureAvailabilityForRelation(string $relation): int
    {
        /** @var Subscription $subscription */
        $subscription = $this->getActiveSubscription();
        if (!$subscription) {
            return 0;
        }

        $limit =  $subscription->getLimitForClassRelation(get_class($this), $relation);

        if (!$limit || $limit === 0) { //unlimited
            return 9999;
        }

        return $limit;
    }

    private function getRestrictedRelations(): ?array
    {
        /** @var Subscription $subscription */
        $subscription = $this->getActiveSubscription();
        if (!$subscription) {
            return null;
        }
        return $subscription->features()
            ->where('type', Feature::TYPE_LIMIT)
            ->where('restricted_model', get_class($this))
            ->pluck('restricted_relation')->toArray();

    }
}
