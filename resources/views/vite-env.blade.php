@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
/// <reference types="vite/client" />

interface ImportMetaEnv {
@foreach ($variables as $variable)
  readonly {!! JsEmitter::validJsObjectKey($variable) !!}: string;
@endforeach
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
