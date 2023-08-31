<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AnnotationPage;
use App\Models\Annotation;
use App\Models\AnnotationBody;
use App\Models\AnnotationSelector;

class AnnotationController extends Controller
{
    //
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getAllByCanvasId()
    {
        // Get Params
        $canvasId = $this->request["canvasId"];

        // Annotation Object
        $annotObj = array();

        $annotPage = AnnotationPage::where([
            ["canvas_id", '=', $canvasId]
        ])->first();

        $annotObj["items"] = array();
        $annotObj["type"] = "AnnotationPage";
        if ($annotPage) {
            $annotObj["id"] = $annotPage->canvas_id;

            $annotationCnt = 0;
            $annotations = Annotation::where([
                ["annotation_page_id", "=", $annotPage->id]
            ])->get();

            if ($annotations) {
                foreach ($annotations as $annotation) {
                    $annotObj["items"][$annotationCnt] = array();
                    // $annotObj["items"][$annotationCnt]["body"] = array();
                    // $annotObj["items"][$annotationCnt]["body"]["purpose"] = $annotation->body_purpose;
                    // $annotObj["items"][$annotationCnt]["body"]["type"] = $annotation->body_type;
                    // $annotObj["items"][$annotationCnt]["body"]["value"] = $annotation->body_value;
                    $annotObj["items"][$annotationCnt]["id"] = $annotation->item_id;
                    $annotObj["items"][$annotationCnt]["motivation"] = $annotation->motivation;
                    $annotObj["items"][$annotationCnt]["type"] = $annotation->type;
                    $annotObj["items"][$annotationCnt]["target"] = array();

                    // Add selectors
                    $selectors = AnnotationSelector::where([
                        ["annotation_id", "=", $annotation->id]
                    ])->get();
                    if ($selectors) {
                        $selectorCnt = 0;
                        $annotObj["items"][$annotationCnt]["target"]["source"] = $annotPage->canvas_id;
                        $annotObj["items"][$annotationCnt]["target"]["selector"] = array();
                        foreach ($selectors as $selector) {
                            $annotObj["items"][$annotationCnt]["target"]["selector"][$selectorCnt] = array();
                            $annotObj["items"][$annotationCnt]["target"]["selector"][$selectorCnt]["type"] = $selector->type;
                            $annotObj["items"][$annotationCnt]["target"]["selector"][$selectorCnt]["value"] = $selector->value;
                            $selectorCnt++;
                        }
                    }

                    // Add bodies
                    $bodies = AnnotationBody::where([
                        ["annotation_id", "=", $annotation->id]
                    ])->get();
                    $annotObj["items"][$annotationCnt]["body"] = array();

                    if ($bodies) {
                        $bodyCnt = 0;
                        foreach ($bodies as $body) {
                            $annotObj["items"][$annotationCnt]["body"][$bodyCnt] = array();
                            $annotObj["items"][$annotationCnt]["body"][$bodyCnt]["purpose"] = $body->purpose;
                            $annotObj["items"][$annotationCnt]["body"][$bodyCnt]["type"] = $body->type;
                            $annotObj["items"][$annotationCnt]["body"][$bodyCnt]["value"] = $body->value;
                            $bodyCnt++;
                        }
                    }

                    // Add Creators
                    $annotObj["items"][$annotationCnt]["creator"] = array();
                    $annotObj["items"][$annotationCnt]["creator"]["id"] = $annotation->creator_id;
                    $annotObj["items"][$annotationCnt]["creator"]["name"] = $annotation->creator_name;
                    $annotObj["items"][$annotationCnt]["creator"]["type"] = $annotation->creator_type;

                    $annotationCnt++;
                }
            }
        }

        return response()->json([
            "annotations" => $annotObj
        ]);
    }

    public function create()
    {
        $annotation = $this->request["annotation"];

        $canvas = $annotation["canvas"];
        $data = json_decode($annotation["data"]);
        $uuid = $annotation["uuid"];
        $itemId = $data->id;
        $motivation = $data->motivation;
        $target = $data->target;
        $type = $data->type;

        // If annotation page exist then create.
        $page = AnnotationPage::where([
            ['canvas_id', '=', $canvas]
        ])->first();
        if (!$page) {
            $page = AnnotationPage::create([
                "canvas_id" => $canvas,
                "uuid" => $uuid
            ]);
        }

        $creator = is_object($data->creator) ? $data->creator : null;
        $annotation = Annotation::create([
            "annotation_page_id" => $page["id"],
            "creator_id" => $creator->id,
            "creator_name" => $creator->name,
            "creator_type" => $creator->type,
            "item_id" => $itemId,
            "motivation" => $motivation,
            "type" => $type
        ]);

        $bodies = $data->body;
        if ($bodies) {
            foreach ($bodies as $body) {
                $annotation->annotationBodies()->create([
                    "purpose" => $body->purpose,
                    "type" => $body->type,
                    "value" => $body->value
                ]);
            }
        }

        // $source = is_object($target) ? $target->source : $target;
        $selectors = is_object($target) ? $target->selector : null;
        if ($selectors) {
            foreach ($selectors as $selector) {
                $annotation->annotationSelectors()->create([
                    "type" => $selector->type,
                    "value" => $selector->value
                ]);
            }
        }

        return response()->json([
            "result" => "success"
        ]);
    }

    public function update()
    {
        $annotObj = $this->request["annotation"];

        $data = json_decode($annotObj["data"]);
        $uuid = $annotObj["uuid"];
        $itemId = $data->id;
        $motivation = $data->motivation;
        $target = $data->target;
        $type = $data->type;

        Annotation::where("item_id", $uuid)->update([
            "motivation" => $motivation,
            "type" => $type
        ]);

        $annotation = Annotation::where("item_id", $uuid)->first();
        if ($annotation != null) {
            $annotation->annotationSelectors()->delete();
            $annotation->annotationBodies()->delete();

            $selectors = is_object($target) ? $target->selector : null;
            if ($selectors) {
                foreach ($selectors as $selector) {
                    $annotation->annotationSelectors()->create([
                        "type" => $selector->type,
                        "value" => $selector->value
                    ]);
                }
            }

            $bodies = $data->body;
            if ($bodies) {
                foreach ($bodies as $body) {
                    $annotation->annotationBodies()->create([
                        "purpose" => $body->purpose,
                        "type" => $body->type,
                        "value" => $body->value
                    ]);
                }
            }
        }

        return response()->json([
            "result" => "success"
        ]);
    }

    public function delete()
    {
        $annoId = $this->request["annoId"];

        $annotation = Annotation::where("item_id", $annoId)->first();
        if ($annotation) {
            $annotation->annotationSelectors()->delete();
            $annotation->annotationBodies()->delete();
        }

        Annotation::where("item_id", $annoId)->delete();

        return response()->json([
            "result" => "success"
        ]);
    }
}