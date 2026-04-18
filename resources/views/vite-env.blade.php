@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
/// <reference types="vite/client" />

interface ImportMetaEnv {
@foreach ($variables as $variable)
  readonly {!! LaravelTsPublish::validJsObjectKey($variable) !!}: string;
@endforeach
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
