<?php

namespace App\Services;

use App\Events\Models\Editable\StoresEditable;
use App\Events\Models\Editable\UpdatesEditable;
use App\Events\Models\Product\{
    DestroysProduct,
    ProductCalculatePriceAvg,
    StoresImageToProduct,
    StoresProduct,
    UpdatesImageToProduct,
    UpdatesProduct
};
use App\Exceptions\Product\CantDelete;
use App\Filters\ProductFilter;
use App\Http\Requests\Product\CreateProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\{
    Diet, Product, ProductPack
};
use Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * @var \App\Services\ProductPackService
     */
    private $packs;

    /**
     * @var \App\Services\PreparationService
     */
    private $preparations;

    /**
     * @var \App\Services\ProductAvailabilityService
     */
    private $availability;

    /**
     * @var \App\Services\NutritionInfoService
     */
    private $nutritionInfo;

    /**
     * ProductService constructor.
     *
     * @param \App\Services\ProductPackService $packs
     * @param \App\Services\PreparationService $preparations
     * @param \App\Services\ProductAvailabilityService $availability
     * @param \App\Services\NutritionInfoService $nutritionInfo
     */
    public function __construct(ProductPackService $packs, PreparationService $preparations, ProductAvailabilityService $availability, NutritionInfoService $nutritionInfo)
    {
        $this->packs = $packs;
        $this->preparations = $preparations;
        $this->availability = $availability;
        $this->nutritionInfo = $nutritionInfo;
    }

    /**
     * @param \App\Filters\ProductFilter $filter
     * @param int|null $perPage
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(ProductFilter $filter, int $perPage = null): LengthAwarePaginator
    {
        return Product::filter($filter)->paginate($perPage);
    }

    /**
     * @param \App\Http\Requests\Product\CreateProductRequest $request
     *
     * @return \App\Models\Product
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function store(CreateProductRequest $request): Product
    {
        $attributes = $request->validated();

        $product = Product::create($attributes);

        $tags = collect($request->get('aliases'))->map(function ($tag) {
            return ['name' => $tag];
        });

        $product->tags()->createMany($tags->toArray());

        $product->preparations()->createMany($request->get('yields') ?? []);

        $product->packs()->createMany($request->get('packs') ?? []);

        $nutsDiet = Diet::whereName(Diet::CONTAIN_NUTS)->first();
        $diets = $request->input('diets', []);
        if ($product->isContainNuts() && $nutsDiet && !\in_array($nutsDiet->id, $diets)) {
            $diets[] = $nutsDiet->id;
        }
        $product->diets()->sync($diets);

        $this->nutritionInfo->storeProduct($product);

        $this->availability->store($product, $request);

        Event::fire(
            new StoresImageToProduct($product)
        );

        Event::fire(
            new ProductCalculatePriceAvg($product)
        );

        Event::fire(
            new StoresProduct($product, $attributes)
        );

        Event::fire(
            new StoresEditable($product, $attributes)
        );

        return $product;
    }

    /**
     * @param \App\Models\Product $product
     * @param \App\Http\Requests\Product\UpdateProductRequest $request
     *
     * @return \App\Models\Product
     */
    public function update(Product $product, UpdateProductRequest $request): Product
    {
        $attributes = $request->validated();
        $oldAttributes = $product->makeOldAttributes($attributes);

        $product->fill($attributes);

        if ($product->isDirty('nutrition_id')) {
            $this->nutritionInfo->updateProduct($product);
        }

        $product->save();

        $this->preparations->update($product, $request->get('yields') ?? []);

        $this->packs->update($product, $request->get('packs') ?? []);

        $product->diets()->sync($request->get('diets') ?? []);

        $product->tags()->delete();
        $tags = collect($request->get('aliases'))->map(function ($tag) { return ['name' => $tag]; });
        $product->tags()->createMany($tags->toArray());

        $this->availability->update($product, $request);

        $product->packs->each(function (ProductPack $pack) { $pack->setPricePerKg()->save(); });

        Event::fire(
            new UpdatesImageToProduct($product)
        );

        Event::fire(
            new UpdatesProduct($product, $attributes)
        );

        Event::fire(
            new UpdatesEditable($product, $attributes, $oldAttributes)
        );

        Event::fire(
            new ProductCalculatePriceAvg($product)
        );

        return tap($product)->fresh();
    }

    /**
     * Destroys Product
     *
     * @param Product $product
     *
     * @return \App\Models\Product
     * @throws \Exception
     */
    public function destroy(Product $product): Product
    {
        if(false === $product->getIsDeletableAttribute()) {
            throw new CantDelete();
        }

        return tap($product)->delete();
    }
}
