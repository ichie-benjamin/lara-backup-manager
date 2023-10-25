<form action="{{route('lara-backup-manager.create')}}" method="post" id="frmNew">
    {{ csrf_field() }}
    <button id="createBackupBtn" type="submit" class="btn btn-info btn-sm"> Create Full Backup</button>
</form>

@push('scripts')

    <script>
        $(document).ready(function () {
            $('#frmNew').on('submit', function () {
                // Show the loading spinner
                $('#createBackupBtn').prop('disabled', true);
                $('#createBackupBtn').html('<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span> Loading...');
            });
        });
    </script>
@endpush
