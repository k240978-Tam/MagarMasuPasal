<?php

namespace Modules\Customers\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customers\Models\Customer;

class CustomerApiController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->with('group')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return $this->respond(
            $customers->getCollection()->map($this->transform(...)),
            meta: ['total' => $customers->total(), 'per_page' => $customers->perPage()],
        );
    }

    public function dues(Customer $customer): JsonResponse
    {
        return $this->respond([
            ...$this->transform($customer),
            'credit_available' => $customer->creditAvailable(),
        ]);
    }

    protected function transform(Customer $customer): array
    {
        return [
            'public_id' => $customer->public_id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'group_name' => $customer->group?->name,
            'current_due' => (float) $customer->current_due,
            'allows_credit' => $customer->allowsCredit(),
        ];
    }
}
