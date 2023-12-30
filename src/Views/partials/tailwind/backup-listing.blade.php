<div>
    @if(Session::has('messages'))
        @foreach (Session::get('messages') as $message)
            <div class="bg-{{ $message['type'] === 'success' ? 'green' : ($message['type'] === 'warning' ? 'yellow' : 'red') }}-100 border-l-4 border-{{ $message['type'] === 'success' ? 'green' : ($message['type'] === 'warning' ? 'yellow' : 'red') }}-500 text-{{ $message['type'] }}-700 p-4 mt-4" role="alert">
                <strong>{{ ucfirst($message['type']) }}!</strong> {{ $message['message'] }}
                <button type="button" class="float-right" data-dismiss="alert" aria-label="Close">
                    <span class="fas fa-times"></span>
                </button>
            </div>
        @endforeach
    @endif

        <form id="frm" action="{{ route('lara-backup-manager.restore_delete') }}" method="post">
            @csrf
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y-2 divide-gray-200 bg-white text-sm">
            <thead>
            <tr>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900" style="width: 1%">#</th>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">Name</th>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">Date</th>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">Size</th>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">Health</th>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900" style="width: 10%">Type</th>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900" style="width: 10%">Download</th>
                <th class="whitespace-nowrap px-4 py-2 font-medium text-gray-900" style="width: 1%">Action</th>
            </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
            @foreach(BackupManager::getBackups() as $index => $backup)
                <tr>
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900" style="text-align: center;">{{ ++$index }}</td>
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $backup['name'] }}</td>
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900 date">{{ $backup['date'] }}</td>
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $backup['size'] }}</td>
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900" style="text-align: center;">
                        @php
                            $okSizeBytes = 1024;
                            $isOk = $backup['size_raw'] >= $okSizeBytes;
                            $text = $isOk ? 'Good' : 'Bad';
                            $icon = $isOk ? 'success' : 'danger';
                        @endphp
                        <span class="badge text-white bg-{{ $icon }} text-{{ $icon }}">{{ $text }}</span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900" style="text-align: center;">
                    <span class="badge text-white bg-{{ $backup['type'] === 'Files' ? 'primary' : 'success' }} text-{{ $backup['type'] === 'Files' ? 'primary' : 'success' }}">
                        {{ $backup['type'] }}
                    </span>
                    </td>
                    <td style="text-align: center">
                        <a class="btn btn-primary btn-sm" href="{{ route('lara-backup-manager.download', [$backup['name']]) }}">
                            <i class="fa fa-download"></i> Download
                        </a>
                    </td>
                    <td style="text-align: center">
                        <input type="checkbox" name="backups[]" class="form-check-input chkBackup" value="{{ $backup['name'] }}">
                    </td>
                </tr>
            @endforeach
            </tbody>
                </table>
            </div>

        <br><br>

            @if (count(BackupManager::getBackups()))
                <input type="hidden" name="type" value="restore" id="type">

                <div class="flex justify-end mt-3">
                    <button type="submit" id="btnSubmit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mr-2" disabled>
                        <i class="fas fa-sync-alt"></i>
                        <strong class="text-xs">Restore</strong>
                    </button>
                    <button type="submit" id="btnDelete" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded mr-2" disabled>
                        <i class="fas fa-trash-alt"></i>
                        <strong class="text-xs">Delete</strong>
                    </button>
                </div>
                <div class="clearfix"></div>
            @endif
    </form>

        <div id="overlay" class="fixed hidden inset-0 bg-black bg-opacity-70 z-50">
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
                <div class="w-20 h-20 border-4 border-white border-t-white rounded-full animate-spin"></div>
                <span class="text-white mt-4 block">Processing, please wait...</span>
            </div>
        </div>

</div>
@push('scripts')
    <script>

        $('.table').DataTable({
            "order": [],
            "responsive": true,
            "pageLength": 10,
            "autoWidth": true,
            aoColumnDefs: [
                {
                    bSortable: false,
                    aTargets: [-1]
                }
            ],
            rowGroup: {
                dataSrc: 2
            }
        });

        var $btnSubmit = $('#btnSubmit');
        var $btnDelete = $('#btnDelete');
        var $type = $('#type');
        var type = 'restore';

        $btnSubmit.on('click', function () {
            $type.val('restore');
            type = 'restore';
        });

        $btnDelete.on('click', function () {
            $type.val('delete');
            type = 'delete';

        });

        $(document).on('click', '.chkBackup', function () {
            var checkedCount = $('.chkBackup:checked').length;

            if (checkedCount > 0) {
                $btnSubmit.attr('disabled', false);
                $btnDelete.attr('disabled', false);
            }
            else {
                $btnSubmit.attr('disabled', true);
                $btnDelete.attr('disabled', true);
            }

            if (this.checked) {
                $(this).closest('tr').addClass('warning');
            }
            else {
                $(this).closest('tr').removeClass('warning');
            }
        });

        $('#frm').submit(function (e) {

            var $this = this;
            var checkedCount = $('.chkBackup:checked').length;
            var $btn = $('#btnSubmit');

            if (!checkedCount) {
                swal("Please select backup(s) first!");
                return false;
            }


            if (checkedCount > 2 && type === 'restore') {
                swal("Please select one or two backups max.");
                return false;
            }

            var msg = 'Continue with restoration process ?';

            if (type === 'delete') {
                msg = 'Are you sure you want to delete selected backups ?';
            }

            swal({
                title: "Confirm",
                text: msg,
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then(function (response) {
                if (response) {
                    $btn.attr('disabled', true);

                    $this.submit();

                    showOverlay();
                }
            });

            return false;
        });

        $('#frmNew').submit(function () {
            this.submit();

            showOverlay();
        });

        function showOverlay() {
            $('#overlay').show();
        }

        function hideOverlay() {
            $('#overlay').show();
        }

    </script>
@endpush


@push('styles')
    <style>
        #overlay {
            position: fixed;
            display: none;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999999999;
        }

        #overlay .overlay-message {
            position: fixed;
            left: 50%;
            top: 57%;
            height: 100px;
            width: 250px;
            margin-left: -120px;
            margin-top: -50px;
            color: #fff;
            font-size: 20px;
            text-align: center;
            font-weight: bold;
        }

        .spinner {
            position: fixed;
            left: 50%;
            top: 40%;
            height: 80px;
            width: 80px;
            margin-left: -40px;
            margin-top: -40px;
            -webkit-animation: rotation .9s infinite linear;
            -moz-animation: rotation .9s infinite linear;
            -o-animation: rotation .9s infinite linear;
            animation: rotation .9s infinite linear;
            border: 6px solid rgba(255, 255, 255, .15);
            border-top-color: rgba(255, 255, 255, .8);
            border-radius: 100%;
        }

        @-webkit-keyframes rotation {
            from {
                -webkit-transform: rotate(0deg);
            }
            to {
                -webkit-transform: rotate(359deg);
            }
        }

        @-moz-keyframes rotation {
            from {
                -moz-transform: rotate(0deg);
            }
            to {
                -moz-transform: rotate(359deg);
            }
        }

        @-o-keyframes rotation {
            from {
                -o-transform: rotate(0deg);
            }
            to {
                -o-transform: rotate(359deg);
            }
        }

        @keyframes rotation {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(359deg);
            }
        }

        table.dataTable tr.group td {
            background-image: radial-gradient(#fff, #eee);
            border: none;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
        }
    </style>
@endpush
