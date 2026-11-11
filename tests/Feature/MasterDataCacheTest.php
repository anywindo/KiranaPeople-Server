<?php

use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('caches all locations and returns from cache on subsequent calls', function () {
    Location::query()->delete();
    $loc1 = Location::create([
        'name' => 'HQ Jakarta',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'radius' => 100,
    ]);
    $loc2 = Location::create([
        'name' => 'Branch Bandung',
        'latitude' => -6.9,
        'longitude' => 107.6,
        'radius' => 150,
    ]);

    expect(Cache::has(Location::CACHE_KEY_ALL))->toBeFalse();

    $locations = Location::getAllCached();
    expect($locations)->toHaveCount(2);
    expect(Cache::has(Location::CACHE_KEY_ALL))->toBeTrue();

    // Verify cache is used by checking query count
    DB::enableQueryLog();
    $cachedLocations = Location::getAllCached();
    expect($cachedLocations)->toHaveCount(2);
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});

it('caches single location and invalidates when updated or deleted', function () {
    $location = Location::create([
        'name' => 'Old HQ',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'radius' => 100,
    ]);

    $cached = Location::findCached($location->id);
    expect($cached)->not->toBeNull();
    expect($cached->name)->toBe('Old HQ');
    expect(Cache::has("master:location:{$location->id}"))->toBeTrue();

    // Updating location should bust cache
    $location->update(['name' => 'New HQ']);
    expect(Cache::has("master:location:{$location->id}"))->toBeFalse();
    expect(Cache::has(Location::CACHE_KEY_ALL))->toBeFalse();

    $reloaded = Location::findCached($location->id);
    expect($reloaded->name)->toBe('New HQ');

    // Deleting location should bust cache
    $location->delete();
    expect(Cache::has("master:location:{$location->id}"))->toBeFalse();
    expect(Cache::has(Location::CACHE_KEY_ALL))->toBeFalse();
});

it('caches shifts and invalidates automatically on save or delete', function () {
    Shift::query()->delete();
    $shift = Shift::create([
        'shift_name' => 'Morning Shift',
        'default_start_time' => '08:00:00',
        'default_end_time' => '17:00:00',
    ]);

    expect(Cache::has(Shift::CACHE_KEY_ALL))->toBeFalse();
    expect(Cache::has(Shift::CACHE_KEY_FIRST))->toBeFalse();

    $allShifts = Shift::getAllCached();
    expect($allShifts)->toHaveCount(1);
    expect(Cache::has(Shift::CACHE_KEY_ALL))->toBeTrue();

    $firstShift = Shift::getFirstCached();
    expect($firstShift->id)->toBe($shift->id);
    expect(Cache::has(Shift::CACHE_KEY_FIRST))->toBeTrue();

    $singleShift = Shift::findCached($shift->id);
    expect($singleShift->shift_name)->toBe('Morning Shift');
    expect(Cache::has("master:shift:{$shift->id}"))->toBeTrue();

    // Update shift -> verify cache is cleared
    $shift->update(['shift_name' => 'Updated Morning Shift']);
    expect(Cache::has(Shift::CACHE_KEY_ALL))->toBeFalse();
    expect(Cache::has(Shift::CACHE_KEY_FIRST))->toBeFalse();
    expect(Cache::has("master:shift:{$shift->id}"))->toBeFalse();

    $reloaded = Shift::findCached($shift->id);
    expect($reloaded->shift_name)->toBe('Updated Morning Shift');
});

it('returns cached location for employee via getLocationCached', function () {
    $loc = Location::create([
        'name' => 'Pabrik Sentul',
        'latitude' => -6.5,
        'longitude' => 106.8,
        'radius' => 200,
    ]);

    $user = User::factory()->create([
        'location_id' => $loc->id,
    ]);

    $userLoc = $user->getLocationCached();
    expect($userLoc)->not->toBeNull();
    expect($userLoc->id)->toBe($loc->id);
    expect($userLoc->name)->toBe('Pabrik Sentul');
    expect(Cache::has("master:location:{$loc->id}"))->toBeTrue();
});
