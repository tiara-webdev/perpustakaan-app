<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // 1. Menampilkan daftar buku lengkap dengan pencarian & filter (Fitur Utama & Plus)
    public function index(Request $request)
    {
        // eager loading (with) untuk mencegah N+1 problem (Critical Thinking untuk performa database)
        $query = Book::with(['author', 'categories']);

        // Filter pencarian berdasarkan judul buku atau nama penulis
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhereHas('author', function($authorQuery) use ($keyword) {
                      $authorQuery->where('name', 'like', "%{$keyword}%");
                  });
            });
        }

        // Filter berdasarkan kategori buku
        if ($request->filled('category_id')) {
            $query->whereHas('categories', function($catQuery) use ($request) {
                $catQuery->where('categories.id', $request->category_id);
            });
        }

        // Filter berdasarkan stok tersedia (stok > 0)
        if ($request->boolean('available')) {
            $query->where('stock', '>', 0);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ], 200);
    }

    // 2. Menambah buku baru (Wajib pilih author_id dan relasi kategori)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'isbn' => 'required|string|unique:books,isbn',
            'published_year' => 'required|integer',
            'price' => 'required|numeric',
            'stock' => 'required|integer|min:0',
            'author_id' => 'required|exists:authors,id',
            'categories' => 'required|array', // Berbentuk array ID kategori
            'categories.*' => 'exists:categories,id'
        ]);

        // Simpan data buku utama
        $book = Book::create([
            'title' => $validated['title'],
            'isbn' => $validated['isbn'],
            'published_year' => $validated['published_year'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'author_id' => $validated['author_id']
        ]);

        // Attach relasi many-to-many ke tabel pivot
        $book->categories()->attach($validated['categories']);

        return response()->json([
            'status' => 'success',
            'message' => 'Buku berhasil ditambahkan',
            'data' => $book->load(['author', 'categories'])
        ], 201);
    }

    // 3. Statistik Sistem (Fitur Nilai Plus)
    public function statistics()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_books' => Book::count(),
                'total_authors' => Author::count(),
                'total_categories' => Category::count(),
            ]
        ], 200);
    }

    // 4. Daftar Buku Stok Menipis / Habis (< 5) (Fitur Nilai Plus)
    public function lowStock()
    {
        $books = Book::with('author')->where('stock', '<', 5)->get();

        return response()->json([
            'status' => 'success',
            'count' => $books->count(),
            'data' => $books
        ], 200);
    }
}