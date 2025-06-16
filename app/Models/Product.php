<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use Translatable;
    protected $fillable = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        // 'price',
        'rate',
        // 'quantity',  
        'image',
        'brand_id',
        'category_id',
        'saleable_id',
        'saleable_type',
    
    ];
    protected $appends = ['name', 'description','is_favorite','image_url', 'images','average_rating', 'ratings_count', 'available_sizes'];

    protected $hidden = ['name_ar', 'name_en', 'description_ar', 'description_en'];

     protected $casts = [
      
        'rate' => 'float',
    ];
    
     public function sizes()
{
    return $this->hasMany(ProductSize::class);
}
public function getRatingsCountAttribute()
    {
        return $this->reviews()->count();
    }

   

        public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function getAverageRatingAttribute()
    {
        return round($this->reviews()->avg('rate'), 1) ?? 0;
    }

        public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
    public function getIsFavoriteAttribute()
    {
        $user = Auth::user();
      
        if (!$user) return false;

        return $this->favorites()->where('user_id', $user->id)->exists();
    }
    public function getImageUrlAttribute()
    {
        return $this->image 
            ? asset( $this->image)
            : null;
    }

  public function getImagesAttribute()
{
    $images = $this->relationLoaded('productImages') ? $this->productImages : $this->productImages()->get();

    return $images->map(function ($image) {
        return $image->image_url;
    })->filter();
}


    public function getNameAttribute()
    {
        return $this->getTranslatedAttribute('name');
    }

    public function getDescriptionAttribute()
    {
        return $this->getTranslatedAttribute('description');
    }
public function getAvailableSizesAttribute()
{
    return $this->sizes->map(function ($size) {
        return [
            'size' => $size->size,
            'price' => $size->price,
            'stock' => $size->stock,
        ];
    });
}
   
  protected static function boot()
    {
        parent::boot();

        if (!app()->runningInConsole() && !request()->is('dashboard*')) {
            static::addGlobalScope('available', function (Builder $builder) {
                $builder->whereHas('sizes', function ($query) {
                    $query->where('stock', '>', 0);
                });
            });
        }
    }
    public function healthIssues(): BelongsToMany
    {
        return $this->belongsToMany(HealthIssue::class, 'health_issue_product', 'product_id', 'health_issue_id');
    }
    
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }


    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function saleable(): MorphTo
    {
        return $this->morphTo();
    }


}