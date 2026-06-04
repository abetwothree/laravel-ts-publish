<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Generators;

use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\FormRequestWriter;
use Illuminate\Foundation\Http\FormRequest;
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
            config()->string('ts-publish.form_requests.transformer_class'),
            ['findable' => $this->findable],
        );
        $this->transformer = $transformer;

        /** @var FormRequestWriter $writer */
        $writer = resolve(config()->string('ts-publish.form_requests.writer_class'));

        return $this->content = $writer->write($this->transformer);
    }

    #[Override]
    public function filename(): string
    {
        return $this->transformer->filename();
    }
}
