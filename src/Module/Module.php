<?php

namespace Xw\DataRetention\Module;

use Xanweb\Module\Module as AbstractModule;
use Xw\DataRetention\Event\Subscriber;
use Xw\DataRetention\Service\Provider as ServiceProvider;

class Module extends AbstractModule
{
    /**
     * {@inheritdoc}
     *
     * @see AbstractModule::pkgHandle()
     */
    public static function pkgHandle(): string
    {
        return 'xw_data_retention';
    }

    /**
     * {@inheritdoc}
     *
     * @see AbstractModule::getServiceProviders()
     */
    protected static function getServiceProviders(): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see AbstractModule::getEventSubscribers()
     */
    protected static function getEventSubscribers(): array
    {
        return [
            Subscriber::class,
        ];
    }
}
