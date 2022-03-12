<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), function ($builder) {
                return $builder->whereUserId(request('user_id'));
            })
            ->when(request('visitor_id'), function (Builder $builder) {
                return $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'));
            })
            ->when(request('lat') && request('lng'), function ($builder) {
                $builder->nearestTo(request('lat'), request('lng'));
            }, function ($builder) {
                $builder->orderBy('id', 'ASC');
            })
            ->latest('id')
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => function ($builder) {
                $builder->where('status', Reservation::STATUS_ACTIVE);
            }])
            ->paginate(20);

        return OfficeResource::collection($offices);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResource
     */
    public function store(): JsonResource
    {
        if(!auth()->user()->tokenCan('office.create')) {
            abort(403);
        }

        $attributes = validator(request()->all(), [
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'address_line1' => ['required', 'string'],
            'hidden' => ['boolean'],
            'price_per_day' => ['required', 'integer', 'min:100'],
            'monthly_discount' => ['integer', 'min:0', 'max:90'],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')]
        ])->validate();
        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        $office = auth()->user()->offices()->create(
            Arr::except($attributes, ['tags'])
        );
        $office->tags()->sync($attributes['tags']);
        return OfficeResource::make($office);
    }

    /**
     * Display the specified resource.
     *
     * @param Office $office
     * @return OfficeResource
     */
    public function show(Office $office): OfficeResource
    {
        $office->loadCount(['reservations' => function ($builder) {
            $builder->where('status', Reservation::STATUS_ACTIVE);
        }])->load(['images', 'tags', 'user']);

        return OfficeResource::make($office);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
