<?php

namespace KalnaLab\Scrive\Resources\AuthProviders\Enums;

/**
 * Class dkMitIDActions
 *
 * Represents a set of actions for the dkMitID authentication provider.
 *
 * Action text shown to the end-user, e.g. 'Log on at [service provider name]' / 'Log på hos [service provider name]'
 */
enum dkMitIDAction: string
{
    case LogOn = 'LogOn';
    case Approve = 'Approve';
    case Confirm = 'Confirm';
    case Accept = 'Accept';
    case Sign = 'Sign';
}
