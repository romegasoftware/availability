<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Support\Effect;
use RomegaSoftware\Availability\Traits\HasAvailability;

/** @internal */
final class TestAvailabilitySubject extends Model implements AvailabilitySubject
{
    use HasAvailability;

    protected $table = 'availability_test_subjects';

    protected $guarded = [];

    protected $casts = [
        'availability_default' => Effect::class,
        'availability_timezone' => 'string',
    ];
}
