<?php

namespace App\Http\Controllers;

use App\Exceptions\BookingException;
use App\Http\Requests\BookingStoreRequest;
use App\Http\Resources\BookingItemResource;
use App\Models\Booking;
use App\Models\TourSlot;
use App\Services\BookingService;
use App\Services\Payment\PaymentGateway;
use App\Services\PaymentService;
use App\Support\UrlToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings)
    {
    }

    public function store(BookingStoreRequest $request, TourSlot $slot): RedirectResponse
    {
        try {
            $booking = $this->bookings->hold($slot, $request->user(), $request->validated());
        } catch (BookingException $e) {
            throw ValidationException::withMessages([$e->field === 'slot' ? 'quantity' : $e->field => $e->getMessage()]);
        }

        return redirect(localized_route('bookings.show', ['booking' => $booking->code]))
            ->with('success', __('messages.booking.held', ['minutes' => $this->bookings->holdMinutes()]));
    }

    /** Tra cứu bằng id mã hóa (UrlToken): token hỏng/sai mục đích → 404; Policy view vẫn áp dụng. */
    public function lookup(Request $request, string $token): Response
    {
        $id = UrlToken::decode(BookingItemResource::URL_TOKEN_PURPOSE, $token);
        abort_if($id === null, 404);

        return $this->show($request, Booking::query()->findOrFail($id));
    }

    public function show(Request $request, Booking $booking): Response
    {
        $this->authorize('view', $booking);
        $booking->load(BookingItemResource::eager());

        return Inertia::render('Bookings/Show', [
            'booking' => BookingItemResource::one($booking),
            'sandboxGateway' => config('disanso.payment.gateway') === 'sandbox',
        ]);
    }

    public function pay(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('pay', $booking);
        if (! app()->bound(PaymentGateway::class) || ! Route::has('payments.sandbox')) {
            return back()->with('error', __('messages.payment.unavailable'));
        }
        try {
            $payment = app(PaymentService::class)->initiate($booking, $request->user());
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect(localized_route('payments.sandbox', ['payment' => $payment->txn_ref]));
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);
        $wasPaid = $booking->status === Booking::PAID;
        try {
            $this->bookings->cancel($booking, $request->user(), 'user_cancelled');
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __($wasPaid ? 'messages.booking.refunded' : 'messages.booking.cancelled'));
    }
}
