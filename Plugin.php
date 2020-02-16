<?php
/**
 * 将 Typecho 的附件上传至七牛云存储中。<a href="https://github.com/lichaoxilcx/typecho-Plugin-QiniuFile" target="_blank">源代码参考</a> &amp; <a href="https://portal.qiniu.com/signup?code=3li4q4loavdxu" target="_blank">注册七牛</a>
 *
 * @package Qiniu File
 * @author LiCxi
 * @version 1.0.0
 * @link http://lichaoxi.com/
 * @date 2018-3-30
 */
require __DIR__ . '/sdk/autoload.php';

use \Qiniu\Storage\UploadManager;
use \Qiniu\Auth;

class QiniuFile_Plugin implements Typecho_Plugin_Interface
{
    // 激活插件
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('QiniuFile_Plugin', 'uploadHandle');
        Typecho_Plugin::factory('Widget_Upload')->modifyHandle = array('QiniuFile_Plugin', 'modifyHandle');
        Typecho_Plugin::factory('Widget_Upload')->deleteHandle = array('QiniuFile_Plugin', 'deleteHandle');
        Typecho_Plugin::factory('Widget_Upload')->attachmentHandle = array('QiniuFile_Plugin', 'attachmentHandle');
        return _t('插件已经激活，需先配置七牛的信息！');
    }


    // 禁用插件
    public static function deactivate()
    {
        return _t('插件已被禁用');
    }


    // 插件配置面板
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $bucket = new Typecho_Widget_Helper_Form_Element_Text('bucket', null, null, _t('空间名称：'));
        $form->addInput($bucket->addRule('required', _t('“空间名称”不能为空！')));

        $accesskey = new Typecho_Widget_Helper_Form_Element_Text('accesskey', null, null, _t('AccessKey：'));
        $form->addInput($accesskey->addRule('required', _t('AccessKey 不能为空！')));

        $sercetkey = new Typecho_Widget_Helper_Form_Element_Text('sercetkey', null, null, _t('SecretKey：'));
        $form->addInput($sercetkey->addRule('required', _t('SecretKey 不能为空！')));

        $domain = new Typecho_Widget_Helper_Form_Element_Text('domain', null, 'http://', _t('绑定域名：'), _t('以 http:// 开头，结尾不要加 / ！'));
        $form->addInput($domain->addRule('required', _t('请填写空间绑定的域名！'))->addRule('url', _t('您输入的域名格式错误！')));

        $savepath = new Typecho_Widget_Helper_Form_Element_Text('savepath', null, 'blog/typecho/', _t('保存路径前缀'), _t('请填写保存路径格前缀，以便数据管理和迁移'));
        $form->addInput($savepath);

        $imgstyle = new Typecho_Widget_Helper_Form_Element_Text('imgstyle', null, '', _t('图片自定义样式名称：'), _t('请到七牛云控制台自定义图片样式，此处填写样式名称'));
        $form->addInput($imgstyle);
    }


    // 个人用户配置面板
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }


    // 获得插件配置信息
    public static function getConfig()
    {
        return Typecho_Widget::widget('Widget_Options')->plugin('QiniuFile');
    }


    // 删除文件
    public static function deleteFile($filepath)
    {
        // // 获取插件配置
        // $option = self::getConfig();
        //
        return true;
    }


    // 上传文件
    public static function uploadFile($file, $content = null)
    {

        // 获取上传文件
        if (empty($file['name'])) return false;

        // 校验扩展名
        $part = explode('.', $file['name']);
        $ext = (($length = count($part)) > 1) ? strtolower($part[$length-1]) : '';
        if (!Widget_Upload::checkFileType($ext)) return false;

        // 获取插件配置
        $option = self::getConfig();
        $date = new Typecho_Date(Typecho_Widget::widget('Widget_Options')->gmtTime);

        // 保存位置
        // $savepath = preg_replace(array('/\{year\}/', '/\{month\}/', '/\{day\}/'), array($date->year, $date->month, $date->day), $option->savepath);
        // $savename = $savepath . sprintf('%u', crc32(uniqid())) . '.' . $ext;
        // if (isset($content))
        // {
        //     $savename = $content['attachment']->path;
        //     self::deleteFile($savename);
        // }

        // 上传文件
        $filename = $file['tmp_name'];
        if (!isset($filename)) return false;

        $upManager = new Qiniu\Storage\UploadManager();
        $auth = new Qiniu\Auth($option->accesskey, $option->sercetkey);
        $token = $auth->uploadToken($option->bucket);
        list($ret, $error) = $upManager->putFile($token, $option->savepath . $file['name'], $filename);

        if ($error == null)
        {
            return array
            (
                'name'  =>  $file['name'],
                'path'  =>  $option->savepath . $file['name'].( $option->imgstyle == '' ? '' : '-'.$option->imgstyle ),
                'size'  =>  $file['size'],
                'type'  =>  $ext,
                'mime'  =>  Typecho_Common::mimeContentType($filename)
            );
        }
        else return false;
    }


    // 上传文件处理函数
    public static function uploadHandle($file)
    {
        return self::uploadFile($file);
    }


    // 修改文件处理函数
    public static function modifyHandle($content, $file)
    {
        return self::uploadFile($file, $content);
    }


    // 删除文件
    public static function deleteHandle(array $content)
    {
        self::deleteFile($content['attachment']->path);
    }


    // 获取实际文件绝对访问路径
    public static function attachmentHandle(array $content)
    {
        $option = self::getConfig();
        return Typecho_Common::url($content['attachment']->path, $option->domain);
    }
}