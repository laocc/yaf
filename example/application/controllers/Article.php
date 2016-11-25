<?php
use laocc\yaf\Controller;

class ArticleController extends Controller
{
    public function indexAction()
    {
        $this->title('Yaf Demo');
        $this->keywords('Yaf Demo');
        $this->description('Yaf Demo');
        $this->assign('value', 'Yaf Plugs');
    }
}
