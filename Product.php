<?php

namespace App\Models;

use App\Contracts\CanBeEdited;
use App\Contracts\HasIndividualEditLogic;
use App\Models\Measurment\ProductPackMeasurement;
use App\Traits\Editable;
use App\Traits\Filterable;
use App\Traits\IndividualEditLogic;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\{
    Model, SoftDeletes
};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo, BelongsToMany, HasMany, MorphMany, MorphOne, MorphTo, MorphToMany
};
use Illuminate\Support\{
    Arr, Collection, Str
};
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class Product extends Model implements HasMedia, CanBeEdited, HasIndividualEditLogic
{
    use Editable, Filterable, HasMediaTrait, SoftDeletes, IndividualEditLogic;

    /**
     * Sku code length
     *
     * @const int
     */
    public const SKU_LENGTH = 32;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'owner_id',
        'owner_type'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'url',
        'sku',
        'brand_id',
        'price_avg',
        'nutrition_id',
        'density_id',
        'supplier_id',
        'food_category_id',
        'in_id'
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = [
        'image'
    ];

    /**
     * @param array|null $request
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSuppliedAttribute(?array $request = []): Collection
    {
        empty($request) ? $order = 'ASC' : $order = $request['order'];

        $suppliers = Supplier::whereSuppliedProductCategory($this->food_category_id, $order)->get();

        return $suppliers->map(function ($supplier) {

            if ($supplier->branches->count() < 2) {
                return [
                    'name' => $supplier->name,
                    'supplies' => $supplier->categories->implode('name', ','),
                    'region' => $supplier->region,
                    'ingredients' => $supplier->products_count,
                    'branches' => []
                ];
            }

            return [
                'name' => $supplier->name,
                'supplies' => $supplier->categories->implode('name', ','),
                'region' => 'Multiple',
                'ingredients' => $supplier->products_count,
                'branches' => $supplier->branches->map(function ($branch) {
                    return [
                        'name' => $branch->name,
                        'supplies' => '',
                        'region' => $branch->region,
                        'ingredients' => 0
                    ];
                })
            ];
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function preparations(): HasMany
    {
        return $this->hasMany(Preparation::class);
    }

    /**
     * @return array
     *
     * TODO: Кто это делал? Разве можно ловить \Exception?
     */
    public function getCurrentMonthAttribute(): array
    {
        $currentMonth = Carbon::now()->format('M');

        try {
            $status =
                $this
                    ->availability()
                    ->whereProductId($this->id)
                    ->where('month', '=', $currentMonth)
                    ->firstOrFail();

            return Arr::get($status->toArray(), 'status');

        } catch (\Exception $exception) {
            return [
                'id' => 0,
                'status' => 'Plentiful local supply',
                'icon_class' => 'active-status'
            ];
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function availability(): HasMany
    {
        return $this->hasMany(ProductAvailability::class);
    }

    /**
     * @return ProductAvailability[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getSeasonAvailabilityAttribute()
    {
        return $this->availability()->orderByRaw('
            CASE
                WHEN month=\'Jan\' THEN 1
                WHEN month=\'Feb\' THEN 2
                WHEN month=\'Mar\' THEN 3
                WHEN month=\'Apr\' THEN 4
                WHEN month=\'May\' THEN 5
                WHEN month=\'June\' THEN 6
                WHEN month=\'July\' THEN 7
                WHEN month=\'Aug\' THEN 8
                WHEN month=\'Sept\' THEN 9
                WHEN month=\'Oct\' THEN 10
                WHEN month=\'Nov\' THEN 11
                WHEN month=\'Dec\' THEN 12
            END
        ')->get()->toArray();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function density(): BelongsTo
    {
        return $this->belongsTo(Density::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'tagable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function diets(): BelongsToMany
    {
        return $this->belongsToMany(Diet::class, 'product_diets');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function foodCategory(): BelongsTo
    {
        return $this->belongsTo(FoodCategory::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function image(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')
            ->where('collection_name', '=', Media::PRODUCT)
            ->latest();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function packs(): HasMany
    {
        return $this->hasMany(ProductPack::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function nutrition(): BelongsTo
    {
        return $this->belongsTo(Nutrition::class);
    }

    /**
     * @return mixed
     */
    public function getNutritionGraphAttribute()
    {
        $age = Auth::user()->settings->display_age_nutrition_graphs;

        return $this
            ->nutritionInfo()
            ->whereAgeGroup($age)
            ->orderBy('index')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function nutritionInfo(): MorphMany
    {
        return $this->morphMany(NutritionInfo::class, 'infoable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param string $mesurement
     * @param float $volume
     *
     * @return float
     */
    public function newPackPrice(string $mesurement, float $volume): float
    {
        return (new ProductPackMeasurement())->newPrice($mesurement, $volume, $this);
    }

    /**
     * @return bool
     */
    public function getIsEditableAttribute(): bool
    {
        $user = Auth::user();

        $isEditable = false;

        if ($user->isRoot()) {
            $isEditable = true;
        }

        if ($user->hasSupplier()) {
            $isEditable = $this->supplier->is($user->supplier);
        }

        return $isEditable;
    }

    public function getIsUsedAttribute(): bool
    {
        $packsIdsArray = optional($this->packs)->pluck('id')->toArray();

        $recipesCount = Recipe::whereHas('ingredients', function (Builder $query) use ($packsIdsArray) {
            return $query->whereIn('product_pack_id', $packsIdsArray);
        })->count();

        return $recipesCount !== 0;
    }

    public function getIsDeletableAttribute(): bool
    {
        return Auth::user()->hasPermissions('product_destroy');
    }

    /**
     * Getter for default waste and note
     * for ingredient, used in App\Http\Resources\Ingredient
     * [$waste, $note]
     *
     * @return array
     */
    public function getDefaultWasteAndNoteAttribute(): array
    {
        /*
         * Product may not have
         * preparations at all,
         * so 'optional()' used here
         * to return 'null'
         */
        $defaultPrepartion = optional($this->preparations)->first(function ($prep) {
            return $prep->default === true;
        });

        $waste = null === $defaultPrepartion
            ? 0
            : 100 - $defaultPrepartion->value;
        $note = optional($defaultPrepartion)->name;

        return [
            $waste,
            $note
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return bool
     */
    public function isWater(): bool
    {
        return Str::contains(strtolower($this->name), 'water');
    }

    /**
     * @return bool
     */
    public function isContainNuts(): bool
    {
        return Str::contains(strtolower($this->name), 'nut');
    }
}
