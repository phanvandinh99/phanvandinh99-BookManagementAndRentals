<?php

namespace App\Http\Controllers\admin;

use Carbon\Carbon;
use App\Models\admin\Rental;
use App\Models\admin\RentalDetail;
use App\Models\admin\User;
use App\Models\admin\Book;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Query chính cho Rental
        $rentals = Rental::query();

        // Tìm kiếm theo mã hóa đơn
        if ($request->has('search')) {
            $searchText = $request->input('search');
            $rentals->where('RentalID', '=', $searchText);
        }

        // Sắp xếp theo thứ tự
        $orderBy = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $rentals->orderBy('RentalID', $orderBy);

        // Phân trang
        $rentals = $rentals->paginate(10)->appends(['order' => $orderBy, 'search' => $request->input('search')]);

        return view('admin.rental.index', compact('rentals'))
            ->with('i', ($rentals->currentPage() - 1) * $rentals->perPage());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $rental = new Rental();
        $users = User::all();
        return view('admin.rental.create', compact('rental', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Xác thực dữ liệu
        $request->validate(Rental::$rules);

        // Tạo hoá đơn mượn
        $rental = new Rental();
        $rental->UserID = $request->input('UserID');
        $rental->DateCreated = Carbon::now(); // Ngày hiện tại
        $rental->Status = 0; // Chưa trả
        $rental->TotalBookCost = 0;
        $rental->TotalRentalPrice = 0;
        $rental->TotalPrice = 0;
        $rental->save();

        $totalBookCost = 0;
        $totalRentalPrice = 0;
        $totalPrice = 0;

        // Lấy dữ liệu chi tiết sách từ form
        $bookIDs = $request->input('BookID', []);
        $endDates = $request->input('EndDate', []);

        // Lưu chi tiết hoá đơn thuê
        foreach ($bookIDs as $key => $bookID) {
            if (!empty($bookID)) {
                $book = Book::find($bookID);
                if ($book) {
                    $rentalDetail = new RentalDetail();
                    $rentalDetail->RentalID = $rental->RentalID;
                    $rentalDetail->BookID = $bookID;
                    $rentalDetail->BookCode = null;
                    $rentalDetail->Quantity = 1;
                    $rentalDetail->StartDate = $rental->DateCreated;
                    $rentalDetail->EndDate = $endDates[$key];
                    $rentalDetail->PaymentDate = null;
                    $rentalDetail->Status = 1;
                    $rentalDetail->save();

                    // Tính tổng giá trị
                    $totalBookCost += $book->SellingPrice;

                    // Tính số ngày thuê (sử dụng Carbon để tính số ngày giữa EndDate và StartDate)
                    $startDate = Carbon::parse($rental->DateCreated);
                    $endDate = Carbon::parse($endDates[$key]);
                    $rentalDays = $startDate->diffInDays($endDate);

                    // Giả sử giá thuê mỗi ngày là 2000
                    $totalRentalPrice += $rentalDays * 2000;
                }
            }
        }

        // Cập nhật tổng giá trị vào hoá đơn
        $totalPrice = $totalBookCost + $totalRentalPrice;
        $rental->TotalBookCost = $totalBookCost;
        $rental->TotalRentalPrice = $totalRentalPrice;
        $rental->TotalPrice = $totalPrice;
        $rental->save();

        return redirect()->route('rental.index')->with('success', 'Tạo hoá đơn mượn thành công!');
    }
}