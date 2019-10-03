<?php


namespace OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors;



use Carbon\Carbon;
use Carbon\CarbonInterface;

abstract class AbstractPlanTypeDateProcessor
{
    /** @var Carbon  */
    protected $startDate;

    /** @var Carbon  */
    protected $expirationDate;

    /** @var Carbon | null */
    protected $testEndsDate;



    /**
     * @var null | int
     */
    private $testingDays;
    /**
     * @var null | string
     */
    private $startAt;

    public function __construct($testingDays = null, $startAt = null)
    {
        $this->startDate = Carbon::now();
        $this->testEndsDate = Carbon::now();
        $this->expirationDate = Carbon::now();
        $this->testingDays = $testingDays;
        $this->startAt = $startAt;
        $this->init();
    }

    protected function init(): void
    {
        $this->applyGeneralStartDateCorrections();
        $this->applyGeneralTestEndDateCorrections();
        $this->applyGeneralExpirationDateCorrections();
    }

    protected function applyGeneralStartDateCorrections(): void
    {
        if ($this->startAt) {
            $this->startDate = Carbon::parse($this->startAt);
        } elseif ($this->testingDays && $this->testingDays > 0) {
            $this->startDate = $this->startDate->addDays($this->testingDays);
        }
    }

    private function applyGeneralTestEndDateCorrections(): void
    {
        if (!$this->testingDays || $this->testingDays === 0) {
            $this->testEndsDate = null;
        }
        if ($this->testingDays && $this->testingDays > 0) {
            $this->testEndsDate = $this->testEndsDate->addDays($this->testingDays);
        }
    }

    protected function applyGeneralExpirationDateCorrections(): void
    {
        if ($this->startAt) {
            $this->expirationDate = $this->expirationDate->addDays(Carbon::now()->diffInDays(Carbon::parse($this->startAt)));
        } elseif ($this->testingDays && $this->testingDays > 0) {
            $this->expirationDate = $this->expirationDate->addDays($this->testingDays);
        }
        $this->expirationDate = $this->expirationDate->endOfDay();
    }

    abstract public function getExpirationDate(): CarbonInterface;

    public function getStartDate(): CarbonInterface
    {
        return $this->startDate;
    }

    public function getTestEndsDate(): ?CarbonInterface
    {
        return $this->testEndsDate;
    }
}
