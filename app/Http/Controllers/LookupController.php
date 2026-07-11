<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupRequest;
use App\Models\BusinessUnit;
use App\Models\Category;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Single-page CRUD for the three lookup tables that feed the transaction
 * forms: categories, payment methods, and business units. One controller keeps
 * the taxonomy management in one place rather than spreading three tiny
 * resource controllers across the route file.
 */
class LookupController extends Controller
{
    /**
     * URL slug -> model class and human label.
     *
     * @var array<string, array{model: class-string, label: string}>
     */
    private const TYPES = [
        'categories' => ['model' => Category::class, 'label' => 'Category'],
        'payment-methods' => ['model' => PaymentMethod::class, 'label' => 'Payment method'],
        'business-units' => ['model' => BusinessUnit::class, 'label' => 'Business unit'],
    ];

    /**
     * Show all three lookup lists on one page.
     */
    public function index(): View
    {
        return view('lookups.index', [
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::orderBy('name')->get(),
            'businessUnits' => BusinessUnit::orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for adding a new lookup item.
     */
    public function create(string $type): View
    {
        $config = $this->configFor($type);

        return view('lookups.create', [
            'type' => $type,
            'label' => $config['label'],
            'model' => new $config['model'](),
        ]);
    }

    /**
     * Store a new lookup item.
     */
    public function store(LookupRequest $request, string $type): RedirectResponse
    {
        $config = $this->configFor($type);
        $modelClass = $config['model'];
        $modelClass::create($request->lookupData($type));

        return redirect()->route('lookups.index')
            ->with('status', "{$config['label']} created successfully.");
    }

    /**
     * Show the form for editing a lookup item.
     */
    public function edit(string $type, int $id): View
    {
        $config = $this->configFor($type);
        $modelClass = $config['model'];

        return view('lookups.edit', [
            'type' => $type,
            'label' => $config['label'],
            'model' => $modelClass::findOrFail($id),
        ]);
    }

    /**
     * Update an existing lookup item.
     */
    public function update(LookupRequest $request, string $type, int $id): RedirectResponse
    {
        $config = $this->configFor($type);
        $modelClass = $config['model'];
        $model = $modelClass::findOrFail($id);
        $model->update($request->lookupData($type));

        return redirect()->route('lookups.index')
            ->with('status', "{$config['label']} updated successfully.");
    }

    /**
     * Delete a lookup item, but only if no transactions reference it. The
     * foreign keys are nullable but constrained, so deleting a referenced
     * row would throw a database error; we check first and give a friendly
     * message instead.
     */
    public function destroy(string $type, int $id): RedirectResponse
    {
        $config = $this->configFor($type);
        $modelClass = $config['model'];
        $model = $modelClass::findOrFail($id);

        if ($model->transactions()->exists()) {
            return redirect()->route('lookups.index')
                ->with('error', "Cannot delete this {$config['label']}: it is used by existing transactions.");
        }

        $model->delete();

        return redirect()->route('lookups.index')
            ->with('status', "{$config['label']} deleted successfully.");
    }

    /**
     * Resolve a URL slug to its model and label, or 404 for an unknown slug.
     *
     * @return array{model: class-string, label: string}
     */
    private function configFor(string $type): array
    {
        return self::TYPES[$type] ?? abort(404);
    }
}
