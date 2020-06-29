<?php


namespace EasySwoole\HttpAnnotation\Annotation;


use EasySwoole\Annotation\Annotation;
use EasySwoole\Http\UrlParser;
use EasySwoole\HttpAnnotation\Annotation\AbstractInterface\CacheInterface;
use EasySwoole\HttpAnnotation\Annotation\AbstractInterface\ParserInterface;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\CircuitBreaker;
use EasySwoole\HttpAnnotation\AnnotationTag\Context;
use EasySwoole\HttpAnnotation\AnnotationTag\Di;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiAuth;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiFail;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroup;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroupAuth;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroupDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiRequestExample;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiResponseParam;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;
use EasySwoole\HttpAnnotation\AnnotationTag\InjectParamsContext;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\Annotation\Method as MethodAnnotation;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\Exception\Annotation\InvalidTag;
use EasySwoole\Utility\File;
use FastRoute\RouteCollector;

class Parser implements ParserInterface
{
    protected $parser;
    protected $CLRF = "\n\n";
    protected $cache = true;
    protected $defaultGroupName = 'default';

    /**
     * @return bool
     */
    public function isCache(): bool
    {
        return $this->cache;
    }

    /**
     * @param bool $cache
     */
    public function setCache(bool $cache): void
    {
        $this->cache = $cache;
    }

    function __construct()
    {
        static::preDefines([
            "POST" => "POST",
            "GET" => "GET",
            'COOKIE' => 'COOKIE',
            'HEADER' => 'HEADER',
            'FILE' => 'FILE',
            'DI' => 'DI',
            'CONTEXT' => 'CONTEXT',
            'RAW' => 'RAW'
        ]);
    }

    /**
     * @return Cache
     * 默认的Cache为单例模式
     */
    function cache():CacheInterface
    {
        return Cache::getInstance();
    }

    public static function preDefines($defines = [])
    {
        foreach ($defines as $key => $val) {
            if (!defined($key)) {
                define($key, $val);
            }
        }
    }

    public function getAnnotationParser(): Annotation
    {
        if (!$this->parser) {
            $annotation = new Annotation();
            $annotation->addParserTag(new Api());
            $annotation->addParserTag(new ApiAuth());
            $annotation->addParserTag(new ApiDescription());
            $annotation->addParserTag(new ApiFail());
            $annotation->addParserTag(new ApiGroup());
            $annotation->addParserTag(new ApiGroupAuth());
            $annotation->addParserTag(new ApiGroupDescription());
            $annotation->addParserTag(new ApiRequestExample());
            $annotation->addParserTag(new ApiResponseParam());
            $annotation->addParserTag(new ApiSuccess());
            $annotation->addParserTag(new CircuitBreaker());
            $annotation->addParserTag(new Context());
            $annotation->addParserTag(new Di());
            $annotation->addParserTag(new InjectParamsContext());
            $annotation->addParserTag(new Method());
            $annotation->addParserTag(new Param());
            $this->parser = $annotation;
        }
        return $this->parser;
    }

    function scanAnnotation(string $pathOrClass): array
    {
        if (is_file($pathOrClass)) {
            $list = [$pathOrClass];
        } else if (is_dir($pathOrClass)) {
            $list = File::scanDirectory($pathOrClass)['files'];
        } else if (class_exists($pathOrClass)) {
            $ref = new \ReflectionClass($pathOrClass);
            $list = [$ref->getFileName()];
        }
        $objectsAnnotation = [];
        if (!empty($list)) {
            foreach ($list as $file) {
                $class = static::getFileDeclareClass($file);
                if ($class) {
                    $objectsAnnotation[$class] = $this->getObjectAnnotation($class);
                }
            }
        }
        return $objectsAnnotation;
    }

    function scanToHtml(string $pathOrClass, ?string $extMd = null)
    {
        $md = $this->scanToMd($pathOrClass);
        $md = "{$extMd}{$this->CLRF}{$md}";
        return str_replace('{$rawMd}',$md,file_get_contents(__DIR__."/docPage.tpl"));
    }

    function scanToMd(string $pathOrClass)
    {
        $final = '';
        $annotations = $this->mergeAnnotationGroup($this->scanAnnotation($pathOrClass));
        foreach ($annotations as $groupName => $group) {
            $markdown = '';
            $hasContent = false;
            $markdown .= "<h1 class='group-title' id='{$groupName}'>{$groupName}</h1>{$this->CLRF}";
            if (isset($group['apiGroupDescription'])) {
                $hasContent = true;
                $markdown .= "<h3 class='group-description'>组描述</h3>{$this->CLRF}";
                $description = $group['apiGroupDescription'];
                $description = $this->getTagDescription($description);
                if(empty($description)){
                    $description = "暂无描述";
                }
                $markdown .= $description."{$this->CLRF}";
            }
            if(isset($group['apiGroupAuth'])){
                $hasContent = true;
                $markdown .= "<h3 class='group-auth'>组权限说明</h3>{$this->CLRF}";
                $params = $group['apiGroupAuth'];
                if (!empty($params)) {
                    $markdown .= "| 字段 | 来源 | 类型 | 描述 | 验证规则 |\n";
                    $markdown .= "| ---- | ---- | ---- | ---- | ---- |\n";
                    /** @var Param $param */
                    foreach ($params as $param) {
                        if(!empty($param->type)){
                            $type = $param->type;
                        }else{
                            $type = '默认';
                        }
                        if(!empty($param->from)){
                            $from = implode(",",$param->from);
                        }else{
                            $from = "不限";
                        }
                        if(!empty($param->description)){
                            $description = $param->description;
                        }else{
                            $description = '-';
                        }
                        $rule = json_encode($param->validateRuleList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $markdown .= "| {$param->name} |  {$from}  | {$type} | {$description} | {$rule} |\n";
                    }
                    $markdown .= "\n\n";
                }
            }

            $markdown .= "<hr class='group-hr'/>{$this->CLRF}";


            /**
             * @var string $methodName
             * @var MethodAnnotation $method
             */
            foreach ($group['methods'] as $methodName => $method) {
                /** @var Api $api */
                $api = $method->getAnnotationTag('Api', 0);
                if ($api) {
                    $methodAnnotation = $method->getAnnotations();
                    $hasContent = true;
                    $deprecated = '';
                    if($api->deprecated){
                        $deprecated .= "<sup class='deprecated'>已废弃</sup>";
                    }
                    $markdown .= "<h2 class='api-method {$groupName}' id='{$groupName}-{$methodName}'>{$methodName}{$deprecated}</h2>{$this->CLRF}";
                    /** @var ApiDescription $description */
                    $description = $method->getAnnotationTag('ApiDescription',0);
                    if($description){
                        $description = $this->getTagDescription($description);
                    }else{
                        $description = $api->description;
                    }
                    $markdown .= "<h4 class='method-description'>接口说明</h4>{$this->CLRF}";
                    $markdown .= "{$description}{$this->CLRF}";

                    $markdown .= "<h3 class='request-part'>请求</h3>{$this->CLRF}";
                    $allow = $method->getAnnotationTag('Method',0);
                    if($allow){
                        $allow = implode(",",$allow->allow);
                    }else{
                        $allow = '不限制';
                    }
                    $markdown .= "<h4 class='request-method'>请求方法:<span class='h4-span'>{$allow}</span></h4>{$this->CLRF}";
                    $markdown .= "<h4 class='request-path'>请求路径:<span class='h4-span'>{$api->path}</span></h4>{$this->CLRF}";
                    $params = $method->getAnnotationTag('ApiAuth');
                    if (!empty($params)) {
                        $markdown .= "<h4 class='auth-params'>权限字段</h4> {$this->CLRF}";
                        $markdown .= "| 字段 | 来源 | 类型 | 描述 | 验证规则 |\n";
                        $markdown .= "| ---- | ---- | ---- | ---- | ---- |\n";
                        /** @var Param $param */
                        foreach ($params as $param) {
                            if(!empty($param->type)){
                                $type = $param->type;
                            }else{
                                $type = '默认';
                            }
                            if(!empty($param->from)){
                                $from = implode(",",$param->from);
                            }else{
                                $from = "不限";
                            }
                            if(!empty($param->description)){
                                $description = $param->description;
                            }else{
                                $description = '-';
                            }
                            $rule = json_encode($param->validateRuleList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $markdown .= "| {$param->name} |  {$from}  | {$type} | {$description} | {$rule} |\n";
                        }
                        $markdown .= "\n\n";
                    }
                    $params = $method->getAnnotationTag('Param');
                    if (!empty($params)) {
                        $markdown .= "<h4 class='request-params'>请求字段</h4> {$this->CLRF}";
                        $markdown .= "| 字段 | 来源 | 类型 | 描述 | 验证规则 |\n";
                        $markdown .= "| ---- | ---- | ---- | ---- | ---- |\n";
                        /** @var Param $param */
                        foreach ($params as $param) {
                            if(!empty($param->type)){
                                $type = $param->type;
                            }else{
                                $type = '默认';
                            }
                            if(!empty($param->from)){
                                $from = implode(",",$param->from);
                            }else{
                                $from = "不限";
                            }
                            if(!empty($param->description)){
                                $description = $param->description;
                            }else{
                                $description = '-';
                            }
                            $rule = json_encode($param->validateRuleList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $markdown .= "| {$param->name} |  {$from}  | {$type} | {$description} | {$rule} |\n";
                        }
                        $markdown .= "\n\n";
                    }
                    if(isset($methodAnnotation['ApiRequestExample'])){
                        $markdown .= "<h4 class='request-example'>请求示例</h4> {$this->CLRF}";
                        $index = 1;
                        foreach ($methodAnnotation['ApiRequestExample'] as $example){
                            $example = $this->getTagDescription($example);
                            if(!empty($example)){
                                $markdown .= "<h5 class='request-example'>请求示例{$index}</h5>{$this->CLRF}";
                                $markdown .= "```\n{$example}\n```{$this->CLRF}";
                                $index++;
                            }
                        }
                    }

                    $markdown .= "<h3 class='response-part'>响应</h3>{$this->CLRF}";
                    $params = $method->getAnnotationTag('ApiResponseParam');
                    if (!empty($params)) {
                        $markdown .= "<h4 class='response-params'>响应字段</h4> {$this->CLRF}";
                        $markdown .= "| 字段 | 来源 | 类型 | 描述 | 验证规则 |\n";
                        $markdown .= "| ---- | ---- | ---- | ---- | ---- |\n";
                        /** @var Param $param */
                        foreach ($params as $param) {
                            if(!empty($param->type)){
                                $type = $param->type;
                            }else{
                                $type = '默认';
                            }
                            if(!empty($param->from)){
                                $from = implode(",",$param->from);
                            }else{
                                $from = "不限";
                            }
                            if(!empty($param->description)){
                                $description = $param->description;
                            }else{
                                $description = '-';
                            }
                            $rule = json_encode($param->validateRuleList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $markdown .= "| {$param->name} |  {$from}  | {$type} | {$description} | {$rule} |\n";
                        }
                        $markdown .= "\n\n";
                    }
                    if(isset($methodAnnotation['ApiSuccess'])){
                        $markdown .= "<h4 class='api-success-example'>成功响应示例</h4> {$this->CLRF}";
                        $index = 1;
                        foreach ($methodAnnotation['ApiSuccess'] as $example){
                            $example = $this->getTagDescription($example);
                            if(!empty($example)){
                                $markdown .= "<h5 class='api-success-example'>成功响应示例{$index}</h5>{$this->CLRF}";
                                $markdown .= "```\n{$example}\n```{$this->CLRF}";
                                $index++;
                            }
                        }
                    }

                    if(isset($methodAnnotation['ApiFail'])){
                        $markdown .= "<h4 class='api-fail-example'>失败响应示例</h4> {$this->CLRF}";
                        $index = 1;
                        foreach ($methodAnnotation['ApiFail'] as $example){
                            $example = $this->getTagDescription($example);
                            if(!empty($example)){
                                $markdown .= "<h5 class='api-fail-example'>失败响应示例{$index}</h5>{$this->CLRF}";
                                $markdown .= "```\n{$example}\n```{$this->CLRF}";
                                $index++;
                            }
                        }
                    }
                }
            }
            if ($hasContent) {
                $markdown .= "<hr class='method-hr'/>{$this->CLRF}";
                $final .= $markdown;
            }
        }
        return $final;
    }


    public function mappingRouter(RouteCollector $collector,string $path, string $controllerNameSpace = 'App\\HttpController\\'): void
    {
        //用于psr规范去除命名空间
        $prefixLen = strlen(trim($controllerNameSpace, '\\'));
        $annotations = $this->scanAnnotation($path);
        /**
         * @var  $class
         * @var ObjectAnnotation $classAnnotation
         */
        foreach ($annotations as $class => $classAnnotation) {
            /** @var MethodAnnotation $method */
            foreach ($classAnnotation->getMethods() as $methodName => $method) {
                /** @var Api $tag */
                $tag = $method->getAnnotationTag('Api', 0);
                if ($tag) {
                    $method = $method->getAnnotationTag('Method', 0);
                    if ($method) {
                        $method = $method->allow;
                    } else {
                        $method = ['POST', 'GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
                    }
                    $realPath = '/' . substr($class, $prefixLen + 1) . '/' . $methodName;
                    $collector->addRoute($method, UrlParser::pathInfo($tag->path), $realPath);
                }
            }
        }
    }

    function mergeAnnotationGroup(array $objectsAnnotation)
    {
        $group = [];
        /** @var ObjectAnnotation $objectAnnotation */
        foreach ($objectsAnnotation as $objectAnnotation) {
            $apiGroup = $this->defaultGroupName;
            if ($objectAnnotation->getGroupInfo()->getApiGroup()) {
                $apiGroup = $objectAnnotation->getGroupInfo()->getApiGroup()->groupName;
            }
            $desc = $objectAnnotation->getGroupInfo()->getApiGroupDescription();
            if ($desc) {
                $group[$apiGroup] = [
                    'apiGroupDescription' => $objectAnnotation->getGroupInfo()->getApiGroupDescription(),
                ];
            }
            foreach ($objectAnnotation->getGroupInfo()->getApiGroupAuth() as $auth) {
                $group[$apiGroup]['apiGroupAuth'][$auth->name] = $auth;
            }
            if (!isset($group[$apiGroup]['methods'])) {
                $group[$apiGroup]['methods'] = [];
            }
            /** @var MethodAnnotation $method */
            foreach ($objectAnnotation->getMethods() as $methodName => $method) {
                $apiName = null;
                $hasApiTag = false;
                $api = $method->getAnnotationTag('Api', 0);
                if ($api) {
                    $apiName = $api->name;
                    $hasApiTag = true;
                } else {
                    $apiName = $methodName;
                }
                $apiGroupTag = $method->getAnnotationTag('ApiGroup',0);
                if($apiGroupTag){
                    $apiGroup = $apiGroupTag->groupName;
                }
                //设置了Api tag的时候，name值禁止相同
                if (isset($group[$apiGroup]['methods'][$apiName]) && $hasApiTag) {
                    throw new InvalidTag("apiName {$apiName} for group {$group} is duplicate");
                }
                $group[$apiGroup]['methods'][$apiName] = $method;
            }
        }
        return $group;
    }

    function getObjectAnnotation(string $class, ?int $filterType = null): ObjectAnnotation
    {
        if($this->cache){
            $object = $this->cache()->get($class);
            if($object instanceof ObjectAnnotation){
                return $object;
            }
        }
        $object = new ObjectAnnotation();
        $ref = new \ReflectionClass($class);
        $object->setReflection($ref);
        $global = $this->getAnnotationParser()->getAnnotation($ref);
        if(isset($global['ApiGroup'])){
            $object->getGroupInfo()->setApiGroup($global['ApiGroup'][0]);
        }else{
            $g = new ApiGroup();
            $g->groupName = $this->defaultGroupName;
            $object->getGroupInfo()->setApiGroup($g);
        }
        if(isset($global['ApiGroupDescription'])){
            $object->getGroupInfo()->setApiGroupDescription($global['ApiGroupDescription'][0]);
        }
        if (isset($global['ApiGroupAuth'])) {
            $object->getGroupInfo()->setApiGroupAuth($global['ApiGroupAuth']);
        }
        foreach ($ref->getMethods($filterType) as $method) {
            $temp = $this->getAnnotationParser()->getAnnotation($method);
            $methodAnnotation = $object->addMethod($method->getName());
            $methodAnnotation->setReflection($method);
            if(isset($temp['ApiGroup'])){
                $gTag = $temp['ApiGroup'][0];
                unset($temp['ApiGroup']);
            }elseif(!empty($object->getGroupInfo()->getApiGroup())){
                $gTag = $object->getGroupInfo()->getApiGroup();
            }else{
                $gTag = new ApiGroup();
                $gTag->groupName = $this->defaultGroupName;
            }
            $methodAnnotation->getGroupInfo()->setApiGroup($gTag);
            if($gTag->groupName == $object->getGroupInfo()->getApiGroup()){
                $methodAnnotation->getGroupInfo()->setApiGroupDescription($object->getGroupInfo()->getApiGroupDescription());
            }else if(isset($temp['ApiGroupDescription'])){
                $methodAnnotation->getGroupInfo()->setApiGroupDescription($temp['ApiGroupDescription'][0]);
            }
            if($gTag->groupName == $object->getGroupInfo()->getApiGroup()){
                foreach ($object->getGroupInfo()->getApiGroupAuth() as $auth){
                    $methodAnnotation->getGroupInfo()->addApiGroupAuth($auth);
                }
            }
            if(isset($temp['ApiGroupAuth'])){
                foreach ($temp['ApiGroupAuth'] as $auth){
                    $methodAnnotation->getGroupInfo()->addApiGroupAuth($auth);
                }
            }
            if(!empty($temp)){
                $methodAnnotation->setAnnotation($temp);
            }
        }

        foreach ($ref->getProperties($filterType) as $property) {
            $p = $object->addProperty($property->getName());
            $p->setReflection($property);
            $temp = $this->getAnnotationParser()->getAnnotation($property);
            if (!empty($temp)) {
                $p->setAnnotation($temp);
            }
        }

        if($this->cache){
            $this->cache()->set($class,$object);
        }

        return $object;
    }

    public static function getFileDeclareClass(string $file): ?string
    {
        $namespace = '';
        $class = NULL;
        $phpCode = file_get_contents($file);
        $tokens = token_get_all($phpCode);
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $namespace .= '\\' . $tokens[$j][1];
                    } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j] === '{') {
                        $class = $tokens[$i + 2][1];
                    }
                }
            }
        }
        if (!empty($class)) {
            if (!empty($namespace)) {
                //去除第一个\
                $namespace = substr($namespace, 1);
            }
            return $namespace . '\\' . $class;
        } else {
            return null;
        }
    }

    private function getTagDescription(ApiDescription $apiDescription)
    {
        $ret = null;
        if ($apiDescription->type == 'file' && file_exists($apiDescription->value)) {
            $ret = file_get_contents($apiDescription->value);
        } else {
            $ret = $apiDescription->value;
        }
        return $this->contentFormat($ret);
    }

    private function contentFormat($content)
    {
        if(is_array($content)){
            return json_encode($content,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        $json = json_decode($content,true);
        if($json){
            $content = json_encode($json,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }else{
            libxml_disable_entity_loader(true);
            $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOCDATA);
            if($xml){
                $content = $xml->saveXML();
            }
        }
        return $content;
    }
}