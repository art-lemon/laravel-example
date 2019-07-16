<?php

namespace App\Http\Controllers\Rest;

use App\Http\Controllers\Controller;
use App\Filters\ProductFilter;
use App\Models\Product;
use App\Services\ProductService;
use App\Http\Requests\Product\{
    CreateProductRequest,
    ProductFilterRequest,
    UpdateProductRequest
};
use App\Http\Resources\Product\{
    ProductList, Product as ProductResource
};

/**
 * @resource Products
 *
 * Products resource
 */
class ProductController extends Controller
{
    /**
     * @var \App\Services\ProductService
     */
    protected $products;

    /**
     * ProductController constructor.
     *
     * @param \App\Services\ProductService $products
     */
    public function __construct(ProductService $products)
    {
        $this->products = $products;
    }

    /**
     * @api {get} /v1/products Show Products
     *
     * @apiParam              {String} [name]                Filter by name (api/v1/products?name=name)
     * @apiParam              {String} [order]               Order by name ASC/DESC (api/v1/products?order=ASC)
     * @apiParam              {Number} [brand]               Filter by brand id
     * @apiParam              {Number} [company]             Filter by company id
     * @apiParam              {Number} [category]            Filter by category id
     * @apiParam              {String} [time]                Order by time created ASC/DESC (api/v1/products?time=ASC)
     * @apiParam              {String} [price]               Order by price ASC/DESC (api/v1/products?price=ASC)
     *
     * @apiSuccess (meta) {Number} current_page Current page
     * @apiSuccess (meta) {Number} from
     * @apiSuccess (meta) {Number} to
     * @apiSuccess (meta) {Number} per_page Per page
     * @apiSuccess (meta) {Number} last_page Last page
     * @apiSuccess (meta) {Number} total Total
     * @apiSuccess (meta) {String} path Path
     *
     * @apiSuccess (links) {String} first First page link
     * @apiSuccess (links) {String} last Last page Link
     * @apiSuccess (links) {String} prev Previous page link
     * @apiSuccess (links) {String} next Next page link
     *
     * @apiSuccess data {Product[]} Products
     *
     * @apiSuccess              {String}    name            Name
     * @apiSuccess              {Date}      updated_at      Creation datetime
     * @apiSuccess              {Date}      created_at      Update datetime
     * @apiSuccess              {Number}    id              Brand id
     * @apiSuccess              {String}    description         Description
     * @apiSuccess              {String}    url                 Website
     * @apiSuccess              {Number}    brand_id            Brand Id
     * @apiSuccess              {Number}    establishment_id    Establishment Id
     * @apiSuccess              {Number}    image               Image jpeg|png|bmp|gif|svg
     * @apiSuccess              {Number}    category            FoodCategory Id
     *
     * @apiError (Error 401) NotAuthorized Not authorized
     *
     * @apiName ShowProducts
     * @apiGroup Products
     * @apiVersion 1.0.0
     *
     * @param \App\Http\Requests\Product\ProductFilterRequest $request
     *
     * @param \App\Filters\ProductFilter $filter
     *
     * @return \App\Http\Resources\Product\ProductList
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(ProductFilterRequest $request, ProductFilter $filter): ProductList
    {
        $this->authorize('list', Product::class);

        return new ProductList(
            $this->products->paginate($filter, $request->get('per_page'))
        );
    }

    /**
     * @api {post} /v1/products Store Product
     *
     * @apiParam                {String}            name                    Name
     * @apiParam                {String}            description             Description
     * @apiParam                {String}            [url]                   Website
     * @apiParam                {Number}            brand_id                Brand Id
     * @apiParam                {Array}             aliases                 Array of aliases ["Potato", "Fry", "Bulba"]
     * @apiParam                {Number}            [image]                 Image jpeg|png|bmp|gif|svg
     * @apiParam (categories)   {Number}            food_category_id        FoodCategory Id
     * @apiParam (diets)        {Array}             [diets]                 Diets Id
     * @apiParam (pack)         {Array}             [pack]                  Pack Model
     * @apiParam (yields)       {String}            name                    Yeild name
     * @apiParam (yields)       {Number}            value                   Yeild volume
     * @apiParam (nutritions)   {Number}            nutritions              Nutritions id
     * @apiParam (density)      {Number}            [density_id]            Density Id
     * @apiParam (season)       {Number}            [season]                Array {["month": "Jan", "season_status_id": 888]}
     *
     * @apiSuccess {String}     name                Name
     * @apiSuccess {Date}       updated_at          Creation datetime
     * @apiSuccess {Date}       created_at          Update datetime
     * @apiSuccess {Number}     id                  Brand id
     * @apiSuccess {String}     name                Name
     * @apiSuccess {String}     description         Description
     * @apiSuccess {String}     url                 Website
     * @apiSuccess {Number}     brand_id            Brand Id
     * @apiSuccess {Number}     image               Image jpeg|png|bmp|gif|svg
     * @apiSuccess {Number}     food_category_id    FoodCategory Id
     * @apiSuccess {Bool}     is_editable         Product can be edited by current user
     *
     * @apiExample {post} Example:
     *
     *  {
     *      "name": "test_product_33",
     *      "description": "describe product product describe",
     *      "url": "http://amazon.com/product.com/123",
     *      "image": "",
     *      "brand_id": 269,
     *      "density_id": 1,
     *      "nutrition_id": 2,
     *      "food_category_id": 1,
     *      "aliases": ["adsf", "asdf", "adf"],
     *      "diets": [1, 2],
     *      "yields": [
     *          {
     *              "name": "asdfasdfadf",
     *              "value": 99
     *          },
     *          {
     *              "name": "asdf",
     *              "value": 333
     *          }
     *      ],
     *      "season": [
     *          {
     *              "month": "Jan",
     *              "season_status_id": 888
     *          }
     *      ],
     *      "packs": [
     *          {
     *              "default": false,
     *              "name": "opa",
     *              "volume": 1,
     *              "measurement": "kg",
     *              "available": true,
     *              "price": 33
     *          }
     *      ]
     *  }
     *
     * @apiError (Error 422) UnprocessableEntity Product data is not valid
     * @apiError (Error 401) NotAuthorized Not authorized
     *
     * @apiName StoreProduct
     * @apiGroup Products
     * @apiVersion 1.0.0
     *
     * @param CreateProductRequest $request
     *
     * @return \App\Http\Resources\Product\Product
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(CreateProductRequest $request): ProductResource
    {
        $this->authorize('store', Product::class);

        return new ProductResource(
            $this->products->store($request)
        );
    }

    /**
     * @api {get} /v1/products/:id Show Product
     * @apiHeader  {String}     Content-Type=application/json
     * @apiHeader  {String}     Accept=application/json
     * @apiHeader  {String}     Authorization=Bearer {token}
     *
     * @apiSuccess {String}    name                Name
     * @apiSuccess {String}    description         Description
     * @apiSuccess {Array}     food_category       FoodCategory
     * @apiSuccess {String}    url                 Website
     * @apiSuccess {Number}    brand_id            Brand Id
     * @apiSuccess {Number}    establishment_id    Establishment Id
     * @apiSuccess {Number}    image               Image jpeg|png|bmp|gif|svg
     * @apiSuccess {Bool}     is_editable         Product can be edited by current user
     * @apiSuccess (categories)               {Number}    food_category_id    FoodCategory Id
     * @apiSuccess (diets)                    {Array}     diets               Diets Id
     * @apiSuccess (pack)                     {Array}     pack                Pack Model
     * @apiSuccess (seasonal availabilities)  {String}    month               Month (E.g. Feb)
     * @apiSuccess (seasonal availabilities)  {Number}    status              Status Id
     * @apiSuccess (yields)                   {String}    name                Yeild name
     * @apiSuccess (yields)                   {Number}    volume              Yeild volume
     * @apiSuccess (density)                  {Number}    density_id          Density Id
     * @apiSuccess (prices)                   {Number}    price_avg           Average price
     *
     * @apiError (Error 422) UnprocessableEntity Product data is not valid
     * @apiError (Error 401) NotAuthorized Not authorized
     *
     * @apiName ShowProduct
     * @apiGroup Products
     * @apiVersion 1.0.0
     *
     * @param \App\Models\Product $product
     *
     * @return \App\Http\Resources\Product\Product
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(Product $product): ProductResource
    {
        $this->authorize('show', Product::class);

        return new ProductResource($product);
    }

    /**
     * @api {put|patch} /v1/products/:id Update Product
     *
     *
     * @apiParam {String}    [name]                Name
     * @apiParam {String}    [description]         Description
     * @apiParam {String}    [url]                 Website
     * @apiParam {Number}    [brand_id]            Brand Id
     * @apiParam {Number}    [nutrition_id]        Nutrition id
     * @apiParam {Image}     [image]               Image jpeg|png|bmp|gif|svg
     * @apiParam (categories)               {Number}    food_category_id    FoodCategory Id
     * @apiParam (diets)                    {Array}  [diets]             Diets Id
     * @apiParam (pack)                     {Array}  [pack]              Pack Model
     * @apiParam (seasonal availabilities)  {String} [month]             Month (E.g. Feb)
     * @apiParam (seasonal availabilities)  {Number} [status]            Status Id
     * @apiParam (yields)                   {String} [name]              Yeild name
     * @apiParam (yields)                   {Number} [volume]            Yeild volume
     * @apiParam (density)                  {Number} [density_id]        Density Id
     *
     * @apiSuccess {String}    name                Name
     * @apiSuccess {String}    description         Description
     * @apiSuccess {String}    url                 Website
     * @apiSuccess {Number}    brand_id            Brand Id
     * @apiSuccess {Number}    nutrition_id        Nutrition id
     * @apiSuccess {Number}    image               Image jpeg|png|bmp|gif|svg
     * @apiSuccess (categories)               {Number}    food_category_id    FoodCategory Id
     * @apiSuccess (diets)                    {Array}     diets               Diets Id
     * @apiSuccess (pack)                     {Array}     pack                Pack Model
     * @apiSuccess (seasonal availabilities)  {String}    month               Month (E.g. Feb)
     * @apiSuccess (seasonal availabilities)  {Number}    status              Status Id
     * @apiSuccess (yields)                   {String}    name                Yeild name
     * @apiSuccess (yields)                   {Number}    volume              Yeild volume
     * @apiSuccess (nutrition_info)           {Array}     nutrition_info      Nutritions graph
     * @apiSuccess (density)                  {Number}    density_id          Density Id
     * @apiSuccess (prices)                   {Number}    price_avg           Average price
     *
     * @apiError (Error 422) UnprocessableEntity Product data is not valid
     * @apiError (Error 401) NotAuthorized Not authorized
     *
     * @apiName UpdateProduct
     * @apiGroup Products
     * @apiVersion 1.0.0
     *
     * @param \App\Http\Requests\Product\UpdateProductRequest $request
     * @param \App\Models\Product $product
     *
     * @return \App\Http\Resources\Product\Product
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        return new ProductResource(
            $this->products->update($product, $request)
        );
    }

    /**
     * @api {delete} /v1/products/:id Delete Product
     *
     * @apiSuccess {String}    name                Name
     * @apiSuccess {String}    description         Description
     * @apiSuccess {String}    url                 Website
     * @apiSuccess {Number}    brand_id            Brand Id
     * @apiSuccess {Number}    establishment_id    Establishment Id
     * @apiSuccess {Number}    image               Image jpeg|png|bmp|gif|svg
     *
     * @apiError (Error 422) UnprocessableEntity Product data is not valid
     * @apiError (Error 401) NotAuthorized Not authorized
     *
     * @apiName DeleteProduct
     * @apiGroup Products
     * @apiVersion 1.0.0
     *
     * @param \App\Models\Product $product
     *
     * @return \App\Http\Resources\Product\Product
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(Product $product): ProductResource
    {
        $this->authorize('destroy', Product::class);

        return new ProductResource(
            $this->products->destroy($product)
        );
    }
}
