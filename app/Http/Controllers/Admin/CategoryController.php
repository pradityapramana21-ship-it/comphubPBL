<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(CategoryRepository $categories): View
    {
        return view('admin.categories.index', [
            'categories' => $categories->allActive(),
            'total' => $categories->countAll(),
        ]);
    }

    public function store(SaveCategoryRequest $request, CategoryRepository $categories): RedirectResponse
    {
        $categories->create($request->categoryAttributes());

        $this->forgetCategoryCache();

        $this->flashSuccess('Kategori ditambahkan.');

        return back();
    }

    public function update(SaveCategoryRequest $request, Category $category, CategoryRepository $categories): RedirectResponse
    {
        $categories->update($category->id, $request->categoryAttributes());

        $this->forgetCategoryCache();

        $this->flashSuccess('Kategori diperbarui.');

        return back();
    }

    /**
     * Menonaktifkan kategori.
     *
     * TIDAK menghapus: kegiatan lama yang memakai kategori ini tetap
     * harus bisa dibaca. Menghapus kategori akan membuat kegiatan lama hilang dari
     * daftar tanpa alasan yang bisa dipahami pengguna.
     */
    public function destroy(Category $category, CategoryRepository $categories): RedirectResponse
    {
        $categories->setActive($category->id, false);

        $this->forgetCategoryCache();

        $this->flashSuccess('Kategori dinonaktifkan. Kegiatan lama tidak terpengaruh.');

        return back();
    }

    private function forgetCategoryCache(): void
    {
        app(\App\Support\CategoryCache::class)->forget();
    }
}
