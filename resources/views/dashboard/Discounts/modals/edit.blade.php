<!-- Edit Discount Modal -->
<div class="modal fade" id="editDiscountModal-{{ $discount->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('dashboard.discounts.update', $discount->id) }}">
      @csrf
      @method('PUT')

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Discount</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" value="{{ old('title', $discount->title) }}" class="form-control" required>
          </div>

          <div class="form-group">
            <label>Precentage (%)</label>
            <input type="number" name="precentage" class="form-control" value="{{ old('precentage', $discount->precentage) }}" min="0" max="100" step="0.01" required>
          </div>

          <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $discount->start_date) }}" required>
          </div>

          <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $discount->end_date) }}">
          </div>

          <div class="form-group">
            <label>Expire Date</label>
            <input type="date" name="expire_date" class="form-control" value="{{ old('expire_date', $discount->expire_date) }}">
          </div>

          <div class="form-group">
            <label>Active</label>
            <select name="is_active" class="form-control" required>
              <option value="1" {{ $discount->is_active ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ !$discount->is_active ? 'selected' : '' }}>No</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Update Discount</button>
        </div>
      </div>
    </form>
  </div>
</div>
