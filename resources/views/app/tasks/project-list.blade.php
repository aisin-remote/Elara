@extends('layouts.app')

@section('title', ($selectedFeature?->name ?? $project->name).' Tasks')
@section('page-title', $selectedFeature?->name ?? $project->name)

@section('content')
    @include('app.tasks._database')
@endsection
