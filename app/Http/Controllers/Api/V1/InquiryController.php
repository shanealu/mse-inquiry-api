<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListInquiriesRequest;
use App\Http\Requests\StoreInquiryRequest;
use App\Http\Resources\InquiryResource;
use App\Http\Resources\InquirySummaryResource;
use App\Models\Inquiry;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InquiryController extends Controller
{
    public function __construct(private InquiryService $inquiries) {}

    public function store(StoreInquiryRequest $request): JsonResponse
    {
        $inquiry = $this->inquiries->store(
            data: $request->validated(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return InquiryResource::make($inquiry)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function index(ListInquiriesRequest $request): AnonymousResourceCollection
    {
        [$column, $direction] = $request->sortColumnAndDirection();

        $query = Inquiry::query()
            ->when($request->query('type'), fn ($q, $v) => $q->ofType($v))
            ->when($request->query('status'), fn ($q, $v) => $q->ofStatus($v))
            ->when($request->query('email'), fn ($q, $v) => $q->forEmail($v))
            ->when($request->query('from'), fn ($q, $v) => $q->createdFrom($v))
            ->when($request->query('to'), fn ($q, $v) => $q->createdTo($v))
            ->orderBy($column, $direction);

        return InquirySummaryResource::collection(
            $query->paginate($request->perPage())->withQueryString(),
        );
    }

    public function show(Inquiry $inquiry, Request $request): InquiryResource
    {
        $this->inquiries->recordView(
            $inquiry,
            $request->ip(),
            $request->userAgent(),
        );

        return InquiryResource::make($inquiry);
    }
}
