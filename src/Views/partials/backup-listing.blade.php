<div>
    @if(Session::has('messages'))
        @foreach (Session::get('messages') as $message)
            <div class="alert alert-{{ $message['type'] }} alert-dismissible fade show" role="alert">
                <strong>{{ $message['type'] }}!</strong> {{ $message['message'] }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endforeach
    @endif

    <form id="frm" action="{{route('lara-backup-manager.restore_delete')}}" method="post">
        {!! csrf_field() !!}

        <table class="table table-bordered table-striped">
            <thead>
            <tr>
                <th style="width: 1%">#</th>
                <th>Name</th>
                <th>Date</th>
                <th>Size</th>
                <th>Health</th>
                <th style="width: 10%">Type</th>
                <th style="width: 10%">Download</th>
                <th style="width: 1%">Action</th>
            </tr>
            </thead>

            <tbody>
            @foreach(BackupManager::getBackups() as $index => $backup)
                <tr>
                    <td style="text-align: center;">{{ ++$index }}</td>
                    <td>{{ $backup['name'] }}</td>
                    <td class="date">{{ $backup['date'] }}</td>
                    <td>{{ $backup['size'] }}</td>
                    <td style="text-align: center;">
                        @php
                            $okSizeBytes = 1024;
                            $isOk = $backup['size_raw'] >= $okSizeBytes;
                            $text = $isOk ? 'Good' : 'Bad';
                            $icon = $isOk ? 'success' : 'danger';
                        @endphp
                        <span class="badge text-white bg-{{ $icon }} text-{{ $icon }}">{{ $text }}</span>
                    </td>
                    <td style="text-align: center;">
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

        <br><br>

        @if (count(BackupManager::getBackups()))
            <input type="hidden" name="type" value="restore" id="type">

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" id="btnSubmit" class="btn btn-success mx-2" disabled>
                    <i class="fa fa-refresh"></i>
                    <strong class="small">Restore</strong>
                </button>
                <button type="submit" id="btnDelete" class="btn btn-danger mx-2" disabled>
                    <i class="fa fa-remove"></i>
                    <strong class="small">Delete</strong>
                </button>
            </div>
            <div class="clearfix"></div>

        @endif

    </form>

    <div id="overlay">
        <div class="spinner"></div>
        <span class="overlay-message">Processing, please wait...</span>
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
