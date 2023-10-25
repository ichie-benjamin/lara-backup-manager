<form action="{{route('lara-backup-manager.create')}}" method="post" id="fileForm">
    {{ csrf_field() }}
    <input name="type" type="hidden" value="file" />
    <button id="createFileBackupBtn" type="submit" class="btn btn-warning btn-sm"> Create File Backup</button>
</form>

@push('scripts')

    <script>
        $(document).ready(function () {
            $('#fileForm').on('submit', function () {
                // Show the loading spinner
                $('#createFileBackupBtn').prop('disabled', true);
                $('#createFileBackupBtn').html('<span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span> Loading...');
            });
        });
    </script>
@endpush
