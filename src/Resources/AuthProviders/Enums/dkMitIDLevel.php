<?php

namespace KalnaLab\Scrive\Resources\AuthProviders\Enums;

/**
 * Class dkMitIDLevels
 *
 * Represents the levels of authentication provided by the Danish MitID authentication service.
 *
 * MitID Level of Assurance (LoA). It is calculated as the minimum of IAL (identity assurance level)
 * and AAL (authentication assurance level). IAL is assigned as part of the registration process with MitID.
 * AAL is calculated on the basis of the authenticators that are used, e.g. only using password would result
 * in a Low AAL, and using 2FA with MitID mobile application would result in Substantial or High.
 */

enum dkMitIDLevel: string
{
    case Low = 'Low';
    case Substantial = 'Substantial';
    case High = 'High';
}