<div class="modal fade" id="createDiscountModal" tabindex="-1" aria-labelledby="createDiscountModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="{{ route('dashboard.discounts.store') }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Discount</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label for="title">Discount Title</label>
            <input type="text" name="title" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="precentage">Precentage (%)</label>
            <input type="number" name="precentage" class="form-control" min="0" max="100" step="0.01" required>
          </div>

          <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" name="start_date" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="end_date">End Date (optional)</label>
            <input type="date" name="end_date" class="form-control">
          </div>

          <div class="form-group">
            <label for="expire_date">Expire Date (optional)</label>
            <input type="date" name="expire_date" class="form-control">
          </div>

          <div class="form-group">
            <label for="is_active">Active</label>
            <select name="is_active" class="form-control" required>
              <option value="1">Yes</option>
              <option value="0">No</option>
            </select>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Add Discount</button>
        </div>
      </div>
    </form>
  </div>
</div>
