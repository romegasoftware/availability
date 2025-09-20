<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use RomegaSoftware\Availability\Contracts\AvailabilitySubject;
use RomegaSoftware\Availability\Traits\HasAvailability;

/** @internal */
final class TestAvailabilitySubjectWithoutCasts extends Model implements AvailabilitySubject
{
    use HasAvailability;

    protected $table = 'availability_test_subjects';

    protected $guarded = [];

    // No casts defined - this allows us to test the trait logic directly
}
