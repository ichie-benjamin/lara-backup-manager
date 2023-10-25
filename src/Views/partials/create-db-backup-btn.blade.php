<form action="{{route('lara-backup-manager.create')}}" method="post" id="dbForm">
    {{ csrf_field() }}
    <input name="type" type="hidden" value="db" />
    <button id="createDBBackupBtn" type="submit" class="btn btn-warning btn-sm"> Create DB Backup</button>
</form>

@push('scripts')

    <script>
        $(document).ready(function () {
            $('#dbForm').on('submit', function () {
                // Show the loading spinner
                $('#createDBBackupBtn').prop('disabled', true);
                $('#createDBBackupBtn').html('<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span> Loading...');
            });
        });
    </script>
@endpush
