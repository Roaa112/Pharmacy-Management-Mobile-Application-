@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
<h1>Discounts</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Discounts</h3>
        <button class="btn btn-primary" data-toggle="modal" data-target="#createDiscountModal">Add Discount</button>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Precentage</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Expire Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($discounts as $discount)
                <tr>
                    <td>{{ $discount->title }}</td>
                    <td>{{ $discount->precentage }}%</td>
                    <td>{{ $discount->start_date }}</td>
                    <td>{{ $discount->end_date ?? '-' }}</td>
                    <td>{{ $discount->expire_date ?? '-' }}</td>
                    <td>
                        @if($discount->is_active)
                        <span class="badge badge-success">Active</span>
                        @else
                        <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-sm btn-info" data-toggle="modal"
                            data-target="#editDiscountModal-{{ $discount->id }}">
                            Edit
                        </button>

                        <form action="{{ route('dashboard.discounts.destroy', $discount) }}" method="POST"
                            class="d-inline"
                            onsubmit="return confirm('Are you sure you want to delete this discount?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>

                {{-- Include Edit Modal --}}
                @include('dashboard.Discounts.modals.edit', ['discount' => $discount])
                @empty
                <tr>
                    <td colspan="7" class="text-center">No discounts found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Include Create Modal --}}
@include('dashboard.Discounts.modals.create')
@stop

@section('css')
{{-- Custom CSS if needed --}}
@stop

@section('js')
<script>
console.log("Discount management loaded!");
</script>
@stop