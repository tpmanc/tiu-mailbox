<?php
namespace tpmanc\tiu;

use tpmanc\tiu\Order;
use tpmanc\tiu\Message;

/**
 *
 */
class Tiu
{
    private $messages;
    private $orders;

    private $stream;
    private $mailLimit = 20;
    private $mailCount;
    private $info;

    public function __construct($mailbox, $user, $password)
    {
        $this->stream = imap_open($mailbox, $user, $password);
        $this->info = imap_check($this->stream);
        if ($this->info === null) {
            throw new Exception('Connection problem');
        }
        $this->mailCount = $this->info->Nmsgs;
        return $this;
    }

    public function setLimit($newLimit)
    {
        $this->mailLimit = $newLimit;
        return $this;
    }

    public function getPageCount()
    {
        return ceil($this->mailCount / $this->mailLimit);
    }

    public function getByPage($pageNumber = 0)
    {
        if ($this->mailCount > 0) {
            $from = $this->mailCount - $this->mailLimit * ($pageNumber + 1) + 1;
            $to = $this->mailCount - $this->mailLimit * $pageNumber;

            // $from = 0;
            // $to = $this->mailCount;
            $overviews = imap_fetch_overview($this->stream, $from . ":" . $to, 0);
            foreach ($overviews as $overview) {
                if (preg_match("/@tiu.ru/", $overview->from)) {
                    $body = imap_fetchbody($this->stream, $overview->msgno, 2);
                    $structure = imap_bodystruct($this->stream, $overview->msgno, 2);
                    if ($structure->encoding == 3) {
                        $body = base64_decode($body);
                    } elseif ($structure->encoding == 1) {
                        $body = imap_8bit($body);
                    } else {
                        $body = imap_qprint($body);
                    }
                    
                    // check if message
                    preg_match("/омер сообщения.+(\d+)\./Ui", $body, $matches);
                    if (!empty($matches[1])) {
                        $id = $matches[1];
                        $link = $this->getMessageLink($body);
                        $title = $this->getTitle($body);
                        $this->messages[] = new Message($id, $link, $title);
                        continue;
                    }

                    // check if order
                    preg_match("/бработать заказ/Ui", $body, $matches);
                    if (!empty($matches[0])) {
                        $link = $this->getOrderLink($body);
                        $id = $this->getId($link);
                        $username = $this->getUsername($body);
                        $this->orders[] = new Order($id, $link, $username);
                        continue;
                    }
                }
            }
        }
        return $this;
    }

    private function getId($link)
    {
        $urlRegexp = "/\/edit\/(\d+)/";
        preg_match($urlRegexp, $link, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }
        return false;
    }

    private function getOrderLink($text)
    {
        $urlRegexp = "/(http|https)\:\/\/my\.tiu\.ru\/cabinet\/order\/.*?(?=\s)/";
        preg_match($urlRegexp, $text, $matches);
        if (!empty($matches[0])) {
            return $matches[0];
        } else {
            $urlRegexp = "/(http|https)\:\/\/tiu\.ru\/cabinet\/order\/.*?(?=\s)/";
            preg_match($urlRegexp, $text, $matches);
            if (!empty($matches[0])) {
                return $matches[0];
            } else {
                var_dump($text);
                throw new \Exception('Link not found');
            }
        }
    }

    private function getMessageLink($text)
    {
        $urlRegexp = "/(http|https)\:\/\/my\.tiu\.ru\/cabinet\/contact_now\/.*?(?=\s)/";
        preg_match($urlRegexp, $text, $matches);
        if (!empty($matches[0])) {
            return $matches[0];
        } else {
            $urlRegexp = "/(http|https)\:\/\/tiu\.ru\/cabinet\/contact_now\/.*?(?=\s)/";
            preg_match($urlRegexp, $text, $matches);
            if (!empty($matches[0])) {
                return $matches[0];
            } else {
                return '';
                var_dump($text);
                throw new \Exception('Link not found');
            }
        }
    }

    private function getTitle($text)
    {
        $urlRegexp = "/Запрос по товару «(.+)»/i";
        preg_match($urlRegexp, $text, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }
        return '';
    }

    private function getUsername($text)
    {
        $urlRegexp = "/покупатель ([йцукенгшщзхъёэждлорпавыфячсмитьбюЙЦУКЕНГШЩЗХЪЁЭЖДЛОРПАВЫФЯЧСМИТЬБЮ]+) оформил/i";
        preg_match($urlRegexp, $text, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }
        return '';
    }

    public function getMailCount()
    {
        return $this->mailCount;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getOrders()
    {
        return $this->orders;
    }

    public function close()
    {
        imap_close($this->stream);
    }
}
