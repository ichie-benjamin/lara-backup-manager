@extends('lara-backup-manager::layout.layout')

@section('title', $title)

@section('header')
    <div class="d-flex gap-3">
        @include('lara-backup-manager::partials.create-backup-btn')
        @include('lara-backup-manager::partials.create-db-backup-btn')
        @include('lara-backup-manager::partials.create-file-backup-btn')
    </div>

@endsection

@section('content')

    @include('lara-backup-manager::partials.backup-listing')

@endsection



