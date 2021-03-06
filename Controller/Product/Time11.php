<?php
/**
 * Created by PhpStorm.
 * User: hoang
 * Date: 13/07/2016
 * Time: 10:34
 */
namespace Magenest\Reservation\Controller\Product;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class Time11
 * @package Magenest\Reservation\Controller\Product
 */
class Time11 extends \Magento\Framework\App\Action\Action
{
    protected $_logger;

    /**
     * @var \Magenest\Reservation\Model\ProductFactory
     */
    protected $_reservationProductFactory;

    /**
     * @var \Magenest\Reservation\Model\SpecialFactory
     */
    protected $_specialFactory;

    /**
     * @var \Magenest\Reservation\Model\ReservationRuleFactory
     */
    protected $_reservationRuleFactory;

    /**
     * @var \Magenest\Reservation\Model\ProductScheduleFactory
     */
    protected $_productScheduleFactory;

    /**
     * @var \Magento\Directory\Model\Currency
     */
    protected $_currency;

    /**
     * @var \Magenest\Reservation\Model\StaffFactory
     */
    protected $_staffFactory;

    /**
     * @var \Magenest\Reservation\Model\StaffRuleFactory
     */
    protected $_staffRuleFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\User\Model\UserFactory
     */
    protected $_userFactory;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magenest\Reservation\Model\ProductFactory $reservationProductFactory,
        \Magento\User\Model\UserFactory $userFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magenest\Reservation\Model\StaffRuleFactory $staffRuleFactory,
        \Magenest\Reservation\Model\StaffFactory $staffFactory,
        \Magento\Framework\App\Action\Context $context,
        \Magenest\Reservation\Model\ProductScheduleFactory $productScheduleFactory,
        \Magenest\Reservation\Model\SpecialFactory $specialFactory,
        \Magenest\Reservation\Model\ReservationRuleFactory $reservationRuleFactory,
        \Magento\Directory\Helper\Data $currency
    ) {
        $this->_logger = $logger;
        $this->_reservationProductFactory = $reservationProductFactory;
        $this->_userFactory = $userFactory;
        $this->_storeManager = $storeManager;
        $this->_staffRuleFactory = $staffRuleFactory;
        $this->_staffFactory = $staffFactory;
        $this->_specialFactory = $specialFactory;
        $this->_reservationRuleFactory = $reservationRuleFactory;
        $this->_productScheduleFactory = $productScheduleFactory;
        $this->_currency = $currency;
        parent::__construct($context);
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $productId = $params['product_id'];
        $date = $params['date'];
        $productPrice = $params['product_price'];
        $schedules = $this->_productScheduleFactory->create()->getCollection()->addFieldToFilter('product_id', $productId);
        $staffModel = $this->_staffFactory->create();
        $staffRuleModel = $this->_staffRuleFactory->create();
        $reservationRuleModel = $this->_reservationRuleFactory->create();
        $specialDateModel = $this->_specialFactory->create()->getCollection();
        $path = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $strExploded = explode("/", $date);
        if ($strExploded[0] < 10) {
            $strExploded[0] = '0' . $strExploded[0];
        }
        if ($strExploded[1] < 10) {
            $strExploded[1] = '0' . $strExploded[1];
        }
        $newDateNum = date_create($strExploded[2] . '-' . $strExploded[1] . '-' . $strExploded[0]);
        $dayFilter = date_format($newDateNum, 'N');
        $newDate = date_format($newDateNum, 'Y/m/d');
        $today = date('Y/m/d');
        if (strcmp($newDate, $today) < 0) {
            return null;
        }
        $scheduleAfterFilters = [];
        foreach ($schedules as $schedule) {
            $oldOrders = unserialize($schedule->getOrders());
            $orderAdded = 0;
            if ($oldOrders) {
                foreach ($oldOrders as $oldOrder) {
                    if ($oldOrder == $newDate && $schedule->getWeekday() == $dayFilter) {
                        $orderAdded = 1;
                        break;
                    }
                }
            }
            if ($orderAdded == 0) {
                if ($schedule->getWeekday() == $dayFilter) {
                    $userModel = $this->_userFactory->create();
                    $activeStaff = $userModel->getCollection()->addFieldToFilter('main_table.user_id', $schedule->getStaffId())->getFirstItem();
                    $activeStaff = $activeStaff->getIsActive();
                    if ($activeStaff == 1) {
                        $thisStaff = $staffModel->getCollection()->addFieldToFilter('staff_id', $schedule->getStaffId())->getFirstItem();
                        $thisStaffAvatars = unserialize($thisStaff->getStaffAvatar());
                        $thisStaffAvatar = null;
                        if ($thisStaffAvatars != null && array_key_exists('file', $thisStaffAvatars)) {
                            $thisStaffAvatar = $thisStaffAvatars['file'];
                        }
                        $thisStaffAmount = 0;
                        if ($thisStaff->getStaffType()) {
                            $thisStaffRule = $staffRuleModel->getCollection()->addFieldToFilter('rule_name', $thisStaff->getStaffType())->getFirstItem();
                            if ($thisStaffRule->getId()) {
                                $thisStaffAmount = $thisStaffAmount + $thisStaffRule['rule_amount'];
                            }
                        }
                        $specialAmount = 0;
                        $specialName = "No Special Event";
                        $specialAdded = 0;
                        if ($specialDateModel) {
                            foreach ($specialDateModel as $specialDate) {
                                $fromTemp = date('Y/m/d H:i:s', strtotime($specialDate->getDateFrom()));
                                $toTemp = date('Y/m/d H:i:s', strtotime($specialDate->getDateTo()));
                                if (strcmp($newDate . ' ' . $schedule->getFromTime() . ':00', $fromTemp) >= 0 && strcmp($newDate . ' ' . $schedule->getToTime() . ':00', $toTemp) <= 0) {
                                    if ($specialDate->getDateOption() == "1") {
                                        $specialAmount = $specialDate->getDateAmount();
                                    } else {
                                        $specialAmount -= $specialDate->getDateAmount();
                                    }
                                    if ($specialAdded == 0) {
                                        $specialName = $specialDate->getDateName();
                                        $specialAdded = 1;
                                    } else {
                                        $specialName .= ', ' . $specialDate->getDateName();
                                    }
                                }
                            }
                        }
                        /**
                         * add reservation rule
                         */
                        $reservationRuleAdded = 0;
                        $earlyOrder = 0;
                        $reservationRulePrice = $productPrice;
                        $reservationRulePrice += $specialAmount;
                        $reservationRulePrice += $thisStaffAmount;
                        $reservationRuleCollection = $reservationRuleModel->getCollection();
                        if ($reservationRuleCollection) {
                            $reservationRuleData = $reservationRuleCollection->getData();
                            usort(
                                $reservationRuleData,
                                function ($item1, $item2) {
                                    if ($item1['rule_unit'] == $item2['rule_unit']) {
                                        return 0;
                                    }
                                    return ($item1['rule_unit'] > $item2['rule_unit']) ? 1 : -1;
                                }
                            );
                            foreach ($reservationRuleData as $reservationRuleDataItem) {
                                $option = $reservationRuleDataItem['rule_option'];
                                switch ($option) {
                                    case 1:
                                        $from = $reservationRuleDataItem['rule_from'];
                                        $to = $reservationRuleDataItem['rule_to'];
                                        if (strcmp('00:00:00', $from) >= 0 && strcmp('23:59:59', $to) <= 0) {
                                            if ($reservationRuleAdded == 0) {
                                                $reservationRuleAdded = 1;
                                                $specialName .= __(', Rush hour');
                                            }
                                            if ($reservationRuleDataItem['rule_function'] == 1) {
                                                $reservationRulePrice += $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 2) {
                                                $reservationRulePrice -= $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 3) {
                                                $reservationRulePrice = $reservationRulePrice * (100 + $reservationRuleDataItem['rule_amount']) / 100;
                                            } elseif ($reservationRuleDataItem['rule_function'] == 4) {
                                                $reservationRulePrice = $reservationRulePrice * (100 - $reservationRuleDataItem['rule_amount']) / 100;
                                            }
                                        }
                                        break;
                                    case 2:
                                        $from = $reservationRuleDataItem['rule_from'];
                                        $to = $reservationRuleDataItem['rule_to'];
                                        $fromArray = explode(",", $from);
                                        $toArray = explode(",", $to);
                                        $fromDay = $fromArray[0];
                                        if ($fromDay == 'Mon') {
                                            $fromDay = 1;
                                        } elseif ($fromDay == 'Tue') {
                                            $fromDay = 2;
                                        } elseif ($fromDay == 'Wed') {
                                            $fromDay = 3;
                                        } elseif ($fromDay == 'Thu') {
                                            $fromDay = 4;
                                        } elseif ($fromDay == 'Fri') {
                                            $fromDay = 5;
                                        } elseif ($fromDay == 'Sat') {
                                            $fromDay = 6;
                                        } elseif ($fromDay == 'Sun') {
                                            $fromDay = 7;
                                        }
                                        $toDay = $toArray[0];
                                        if ($toDay == 'Mon') {
                                            $toDay = 1;
                                        } elseif ($toDay == 'Tue') {
                                            $toDay = 2;
                                        } elseif ($toDay == 'Wed') {
                                            $toDay = 3;
                                        } elseif ($toDay == 'Thu') {
                                            $toDay = 4;
                                        } elseif ($toDay == 'Fri') {
                                            $toDay = 5;
                                        } elseif ($toDay == 'Sat') {
                                            $toDay = 6;
                                        } elseif ($toDay == 'Sun') {
                                            $toDay = 7;
                                        }
                                        if (strcmp($dayFilter.'00:00:00', $fromDay.$fromArray[1]) >= 0 && strcmp($dayFilter.'23:59:59', $toDay.$toArray[1]) <= 0) {
                                            if ($reservationRuleAdded == 0) {
                                                $reservationRuleAdded = 1;
                                                $specialName .= __(', Rush hour');
                                            }
                                            if ($reservationRuleDataItem['rule_function'] == 1) {
                                                $reservationRulePrice += $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 2) {
                                                $reservationRulePrice -= $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 3) {
                                                $reservationRulePrice = $reservationRulePrice * (100 + $reservationRuleDataItem['rule_amount']) / 100;
                                            } elseif ($reservationRuleDataItem['rule_function'] == 4) {
                                                $reservationRulePrice = $reservationRulePrice * (100 - $reservationRuleDataItem['rule_amount']) / 100;
                                            }
                                        }
                                        break;
                                    case 3:
                                        $from = $reservationRuleDataItem['rule_from'];
                                        $to = $reservationRuleDataItem['rule_to'];
                                        $fromArray = explode(",", $from);
                                        $toArray = explode(",", $to);
                                        if ($fromArray[0] < 10) {
                                            $fromArray[0] = '0' . $fromArray[0];
                                        }
                                        if ($toArray[0] < 10) {
                                            $toArray[0] = '0' . $toArray[0];
                                        }
                                        $dayFilter = date_format($newDateNum, 'd');
                                        if (strcmp($dayFilter.'00:00:00', $fromArray[0].','.$fromArray[1]) >= 0 && strcmp($dayFilter.'23:59:59', $toArray[0].','.$toArray[1]) <= 0) {
                                            if ($reservationRuleAdded == 0) {
                                                $reservationRuleAdded = 1;
                                                $specialName .= __(', Rush hour');
                                            }
                                            if ($reservationRuleDataItem['rule_function'] == 1) {
                                                $reservationRulePrice += $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 2) {
                                                $reservationRulePrice -= $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 3) {
                                                $reservationRulePrice = $reservationRulePrice * (100 + $reservationRuleDataItem['rule_amount']) / 100;
                                            } elseif ($reservationRuleDataItem['rule_function'] == 4) {
                                                $reservationRulePrice = $reservationRulePrice * (100 - $reservationRuleDataItem['rule_amount']) / 100;
                                            }
                                        }
                                        break;
                                    case 4:
                                        $from = $reservationRuleDataItem['rule_from'];
                                        $to = $reservationRuleDataItem['rule_to'];
                                        $fromDate = date('m/d H:i:s', strtotime($from));
                                        $toDate = date('m/d H:i:s', strtotime($to));
                                        if (strcmp(date_format($newDateNum, 'm/d') . ' ' . '00:00:00', $fromDate) >= 0
                                            && strcmp(date_format($newDateNum, 'm/d') . ' ' . '23:59:59', $toDate) <= 0
                                        ) {
                                            if ($reservationRuleAdded == 0) {
                                                $reservationRuleAdded = 1;
                                                $specialName .= __(', Rush hour');
                                            }
                                            if ($reservationRuleDataItem['rule_function'] == 1) {
                                                $reservationRulePrice += $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 2) {
                                                $reservationRulePrice -= $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 3) {
                                                $reservationRulePrice = $reservationRulePrice * (100 + $reservationRuleDataItem['rule_amount']) / 100;
                                            } elseif ($reservationRuleDataItem['rule_function'] == 4) {
                                                $reservationRulePrice = $reservationRulePrice * (100 - $reservationRuleDataItem['rule_amount']) / 100;
                                            }
                                        }
                                        break;
                                    case 5:
                                        /**
                                         * calculate interval time between this order to the schedule
                                         */
                                        $diff = date_diff(date_create(date('Y/m/d')), date_create($newDate));
                                        $diffInt = intval($diff->format("%a"));
                                        if ($diffInt > 0 && $diffInt >= $reservationRuleDataItem['rule_from']) {
                                            if ($earlyOrder == 0) {
                                                $specialName .= __(', Pre-order');
                                                $earlyOrder = 1;
                                            }
                                            if ($reservationRuleDataItem['rule_function'] == 1) {
                                                $reservationRulePrice += $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 2) {
                                                $reservationRulePrice -= $reservationRuleDataItem['rule_amount'];
                                            } elseif ($reservationRuleDataItem['rule_function'] == 3) {
                                                $reservationRulePrice = $reservationRulePrice * (100 + $reservationRuleDataItem['rule_amount']) / 100;
                                            } elseif ($reservationRuleDataItem['rule_function'] == 4) {
                                                $reservationRulePrice = $reservationRulePrice * (100 - $reservationRuleDataItem['rule_amount']) / 100;
                                            }
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                        }
                        if (intval($reservationRulePrice) <= 0) {
                            $reservationRulePrice = 0;
                        }
                        $reservationRulePrice = number_format((float)$reservationRulePrice, 2, '.', '');
                        array_push(
                            $scheduleAfterFilters,
                            [
                                'staff_id' => $schedule->getStaffId(),
                                'staff_name' => $thisStaff->getStaffName(),
                                'staff_avatar' => $path . 'reservation/user/avatar' . $thisStaffAvatar,
                                'staff_intro' => $thisStaff->getStaffIntro(),
                                'staff_amount' => $thisStaffAmount,
                                'event_name' => $specialName,
                                'event_amount' => $reservationRulePrice,
                                'symbol' => $this->_storeManager->getStore()->getCurrentCurrencyCode(),
                                'date' => $newDate
                            ]
                        );
                    }
                }
            }
        }
        $resultArray = json_encode($scheduleAfterFilters);
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($resultArray);
        return $resultJson;
    }
}
