@extends('layout.main')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-4">
            @if ($message = Session::get('success'))
            <div class="alert alert-info">
                {{ $message }}
            </div>
            @endif
            <div class="card">
                <div class="card-header">
                    Registration
                </div>
                <div class="card-body">
                    <form action="{{ route('profile') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        @foreach ($data as $row)
                            <div class="form-group mb-3">
                                <input type="text" name="name" value="{{ $row->name }}" placeholder="Name" class="form-control">
                                @if ($errors->has('name'))
                                    <span class="text-danger">{{ $errors->first('name') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <input type="email" name="email" value="{{ $row->email }}" placeholder="Email" class="form-control">
                                @if ($errors->has('email'))
                                    <span class="text-danger">{{ $errors->first('email') }}</span>
                                @endif
                            </div>11
                            <div class="form-group mb-3">
                                <input type="password" name="password" placeholder="******" class="form-control">
                                @if ($errors->has('password'))
                                    <span class="text-danger">{{ $errors->first('password') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                    <input type="file" name="user_image" class="form-control">
                                @if ($row->user_image != '')
                                    <img src="{{ asset('images/'.$row->user_image) }}" name="user_image" class="form-control">
                                @else
                                    <input type="hidden" value="{{ $row->user_image }}" name="hidden_user_image" class="form-control">
                                @endif
                                @if ($errors->has('user_image'))
                                    <span class="text-danger">{{ $errors->first('user_image') }}</span>
                                @endif
                            </div>
                            <div class="d-grid mx-auto">
                                <button type="submit" class="btn btn-dark btn-block">Save</button>
                            </div>
                        @endforeach
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
