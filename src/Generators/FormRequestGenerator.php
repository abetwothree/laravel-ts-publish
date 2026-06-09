<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\FormRequestWriter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * @extends CoreGenerator<FormRequest>
 */
class FormRequestGenerator extends CoreGenerator
{
    public protected(set) FormRequestTransformer $transformer;

    #[Override]
    public function generate(): string
    {
        /** @var FormRequestTransformer $transformer */
        $transformer = resolve(
            Config::string('ts-publish.form_requests.transformer_class', FormRequestTransformer::class),
            ['findable' => $this->findable],
        );
        $this->transformer = $transformer;

        /** @var FormRequestWriter $writer */
        $writer = resolve(Config::string('ts-publish.form_requests.writer_class', FormRequestWriter::class));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}
