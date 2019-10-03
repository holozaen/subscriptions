<?php


namespace OnlineVerkaufen\Subscriptions\Console\Commands;

use Illuminate\Console\Command;
use OnlineVerkaufen\Subscriptions\Models\Subscription;


class RenewExpiringSubscriptionsCommand extends Command
{
    protected /** @noinspection ClassOverridesFieldOfSuperClassInspection */
        $signature = 'subscriptions:renew';

    protected /** @noinspection ClassOverridesFieldOfSuperClassInspection */
        $description = 'renews subscriptions expiring tomorrow night';

    public function handle(): void
    {
        $this->info('renew recurring subscriptions expiring tomorrow night...');

        /** @var Subscription $subscription */
        foreach(Subscription::expiring()->recurring()->cursor() as $subscription)
        {
            $subscription->model->renewExpiringSubscription();
            /** @noinspection DisconnectedForeachInstructionInspection */
            $this->output->write('.');
        }

        $this->info("\nDone!");
    }
}
