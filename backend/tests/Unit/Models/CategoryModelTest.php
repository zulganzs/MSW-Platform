<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_has_many_reports(): void
    {
        $category = Category::factory()->create();

        $this->assertInstanceOf(HasMany::class, $category->reports());
    }
}
