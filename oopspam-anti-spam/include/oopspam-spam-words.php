<?php
/**
 * Spam words and phrases bundled with the plugin.
 *
 * Source: https://github.com/OOPSpam/spam-words (one word or phrase per line).
 * Matching is case-insensitive; multi-word lines are treated as exact phrases.
 *
 * To add a language, add an entry below. The "Blocked keywords and phrases"
 * settings field and its pre-fill buttons pick up new entries automatically.
 *
 * @return array<string, array{label: string, words: string}>
 */
function oopspam_get_spam_words()
{
    $oopspam_words_en = <<<'OOPSPAM_SPAM_WORDS_EN'
#1
100% more
100% free
100% satisfied
Additional income
Be your own boss
Best price
Big bucks
Billion
Cash bonus
Cents on the dollar
Consolidate debt
Double your cash
Double your income
Earn extra cash
Earn money
Eliminate bad credit
Extra cash
Extra income
Expect to earn
Fast cash
Financial freedom
Free access
Free consultation
Free gift
Free hosting
Free info
Free investment
Free membership
Free money
Free preview
Free quote
Free trial
Full refund
Get out of debt
Get paid
Giveaway
Guaranteed
Increase sales
Increase traffic
Incredible deal
Lower rates
Lowest price
Make money
Million dollars
Miracle
Money back
Once in a lifetime
One time
Pennies a day
Potential earnings
Prize
Promise
Pure profit
Risk-free
Satisfaction guaranteed
Save big money
Save up to
Special promotion
Act now
Apply now
Become a member
Call now
Click below
Click here
Get it now
Do it today
Don’t delete
Exclusive deal
Get started now
Important information regarding
Information you requested
Instant
Limited time
New customers only
Order now
Please read
See for yourself
Sign up free
Take action
This won’t last
Urgent
What are you waiting for?
While supplies last
Will not believe your eyes
Winner
Winning
You are a winner
You have been selected

Bulk email
Buy direct
Cancel at any time
Check or money order
Congratulations
Confidentiality
Cures
Dear friend
Direct email
Direct marketing
Hidden charges
Human growth hormone
Internet marketing
Lose weight
Mass email
Meet singles
Multi-level marketing
No catch
No cost
No credit check
No fees
No gimmick
No hidden costs
No hidden fees
No interest
No investment
No obligation
No purchase necessary
No questions asked
No strings attached
Not junk
Notspam
Obligation
Passwords
Requires initial investment
Social security number
This isn’t a scam
This isn’t junk
This isn’t spam
Undisclosed
Unsecured credit
Unsecured debt
Unsolicited
Valium
Viagra
Vicodin
We hate spam
Weight loss
Xanax
Accept credit cards
Ad
All new
As seen on
Bargain
Beneficiary
Billing
Bonus
Cards accepted
Cash
Certified
Cheap
Claims
Clearance
Compare rates
Credit card offers
Deal
Debt
Discount
Fantastic
In accordance with laws
Income
Investment
Join millions
Lifetime
Loans
Luxury
Marketing solution
Message contains
Mortgage rates
Name brand
Offer
Online marketing
Opt in
Pre-approved
Quote
Rates
Refinance
Removal
Reserves the right
Score
Search engine
Sent in compliance
Subject to…
Terms and conditions
Trial
Unlimited
Warranty
Web traffic
Work from home
OOPSPAM_SPAM_WORDS_EN;

    $oopspam_words_zh = <<<'OOPSPAM_SPAM_WORDS_CN'
18禁
a片
caonima
cnm
caotama
cao你
cao你妈
fa轮
hjt
jb
jzm
nmsl
sb
vpn
zhengfu
zheng府
zf
zedong
亂倫
噴精
姦淫
屄
屄毛
幹炮
幹砲
擠乳汁
溫家寶
無毛穴
獸交
爱女人
爱液
扒穴
拔屄自拍
白虎阴穴
白虎少妇
白浆四溅
包二奶
薄熙来
薄码
暴奸
爆乳娘
爆草
爆操
被操
被插
被干
逼痒
逼奸
博彩
擦你妈
操我
操死
操死你
操你妈
操你奶
操你姐
操逼
操你祖宗
操你大爷
操你妹
操穴
操屄
操他妈
操你全家
操烂
操妻
操你嘴
操死她
操b
操它妈
曹刚川
草你祖宗
草你大爷
草你妈
草你吗
草bi
草她妈
草拟吗
草你娘
草他妈
厕所盗摄
厕奴
插阴茎
插逼
插妹妹
插穴止痒
插死她
插比
插b
插你
陈同海
陈水扁
陈良宇
成人片
成人网站
成人图
成人电
成人文
成人视
成人自拍
成人小
惩公安
吃鸡巴
抽插
臭鸡八
臭鸡吧
床上写真
催情藥
催情药
催眠水
催情粉
大奶子
大肉棒
大奶头
大明运气咒
大力抽送
大血逼
大雞巴
大鸡巴
大傻b
戴秉国
盗撮
邓小瓶
邓小平
邓爷爷
东北独立
杜世成
杜德印
法lun
法伦功
法维权
法一轮
法正乾
法车仑
法轮佛
法轮
翻墙
反共复清
反华示威
肥逼
粉穴
风艳阁
干你妈
干的爽
干穴
干你娘
干你妹
干死你
干你全家
肛交
肛门
肛门拳交
给你爽
根达亚文明
共残主义
狗娘养
灌满精液
郭金龙
国峰
国锋
含屌
喝血社会
黑毛屄
胡云松
胡王八
胡谨涛
胡錦濤
胡海峰
胡主席
胡春华
胡温
虎精逃
华国
黄色电影
回良玉
混蛋
激情电
激情妹
激情炮
激情短
激情小说
鸡巴
鸡奸
集体自杀
挤乳汁
几吧
妓女
家宝
奸幼
奸杀
奸污
践货
贱人
贱比
江泽民
江澤民
江x
江某某
脚奴
街头扒衣
金毛穴
锦涛
精子射在
警察说保
警方包庇
警车雷达
警察殴打
警察的幌
就去日
菊花洞
巨乳
恐怖份子
恐怖分子
抠穴
口淫
口交
口活
口内爆射
狂乳激揺
拉登
浪逼
浪叫
雷管
李小鹏
李鹏
李克强
李洪志
李世民
習近平
莲花逼
炼大法
梁光烈
两会又三
两会代
刘延东
露逼
露b
乱伦类
乱奸
乱伦小
轮子功
轮奸
轮功
伦理毛
伦理电影
伦理片
伦理大
裸舞视
裸体
裸聊网
妈个逼
妈了个逼
妈了逼
麻果配
麻果丸
麻古
玛雅历法
卖淫
满狗
肏屄
氓培训
猫贼洞
毛主席
毛遮洞
毛则东
毛泽东
毛贼东
毛澤東
美女高潮
美艳少妇
妹按摩
妹上门
门按摩
门保健
蒙汗药
孟建柱
迷昏口
迷奸
迷魂香
迷幻型
迷幻药
迷情药
迷幻藥
迷魂药
迷藥
迷奸药
迷昏药
迷昏藥
迷魂藥
迷情水
谜奸药
秘唇
蜜穴
密穴
民抗议
明慧网
摸阴蒂
某锦涛
母奸
母子乱伦
母子奸情
奶子
男奴
男女交欢
内射
嫩穴
嫩b
嫩逼
嫩屄
嫩bb
嫩阴
你妈死了
你日妈
你妈逼
娘西皮
娘了个比
娘两腿之间
浓精
怒的志愿
女优
女任职名
女人和狗
女技师
女激情
女優
女上门
女被人家搞
拍肩神药
炮友
喷尿
屁眼
平叫到床
平惨案
仆不怕饮
普通嘌
期货配
奇迹的黄
奇淫散
强暴
强硬发言
强奸你妹
强奸
强权政府
巧淫奸戏
情色
全裸
全家死绝
全家死光
群奸暴
群体性事
群起抗暴
群交
绕过封锁
人类灭亡
人兽
人妻
人妻做爱
人妻熟女
人妻榨乳
人体炸弹
人妻色诱
日中断交
日你妹
日烂
日你全家
日死你
日你妈
日逼
肉棒
肉壶
肉蒲团
肉便器
肉茎
肉淫器吞精
肉棍
肉穴
肉逼
肉棍干骚妇
肉唇
肉洞
乳交
软弱的国
三秒倒
三级片
三挫
三唑
搔逼
骚妇
骚浪美女
骚贱
骚嘴
骚姐姐
骚母
骚洞
骚穴
骚逼
骚妹
骚浪
骚乳
骚女
骚水
色妹妹
色电影
色视频
色猫
色小说
色书库
傻臂
傻逼
傻b
傻避
煞笔
煞逼
少妇
舌头穴
射颜
射爽
社会主义灭亡
沈跃跃
十八禁
兽奸
熟妇人妻
爽片
爽穴
死全家
死逼
苏树林
酥穴
塔利班
蘚鮑
台湾
台独
台湾独立
体奸
天黯门
天岸门
天案们
天安门大屠杀
天氨门
天安门
天胺门
天案门
舔脚
舔屄
铁凝
偷窥图片
推背图
退党
吞精
王胜俊
王岐山
王洛林
王太华
王鸿举
瘟总理
瘟加饱
瘟假饱
温影帝
温家堡
温切斯特
温家宝
温家某
吴定富
吴邦国
吸精少女
习近平
洗肠射尿
下流地带
下贱
销魂洞
小穴
小平遗言
小逼崽子
小平遗嘱
新唐人
性虐
性奴
性爱
性爱图库
颜射
艳妇淫女
要射精了
要泄了
要射了
要人权
耀邦
叶剑英
夜激情
液体炸
一夜欢
遗嘱小平
阴屄
阴b
阴茎
阴唇
阴部
阴締
淫贱
淫叫
淫液
淫娃
淫魔舞
淫情
淫肉
淫蜜
淫蕩
淫汁
淫姐
淫妞
淫逼
淫亂潮吹
淫荡
淫騷妹
淫情女
淫奴
淫兽
淫兽学
淫水
淫妇
淫穴
淫水爱液
淫母
淫亂
幼交
玉穴
原味内裤
原味内衣
援交妹
杂种操的
则民爷爷
泽民
炸立交
炸弹遥控
炸药的制
炸药
炸弹
炸药配
炸弹教
炸鸟巢
炸广州
炸药制
炸学校
张志国
张德江
张荣坤
政治局常委
政fu
政府
制服狩
中国人权
中日断交
朱镕基
猪容鸡
诸世纪
主席
主席像
自焚
自拍美穴
自慰抠穴
自慰
总竖鸡
总理
总书记
钻插
做爱
妞上门
嫖妓指南
嫖鸡
嫖俄罗
門服務
陰道
陰唇
陰戶
掰穴
騷浪
OOPSPAM_SPAM_WORDS_CN;

    $oopspam_words_ru = <<<'OOPSPAM_SPAM_WORDS_RU'
# один
50% скидка
0% риск
Акции
Банкротство
Без вложений
Без обязательств
Без обязательств
Без опыта
Без риска
Безлимитный
Беспроцентный кредит
Безрисковый
Бесплатная консультация
Бесплатная установка
Бесплатно
Бесплатный доступ
Бесплатный сайт
Бесплатный тестовый доступ
Бизнес на дому
Бонус
Бренд
В день
в неделю
в месяц
Ваш статус
Виагра
Вклад
Возврат денег
Вы были выбраны
Вы победитель
Выгодная сделка
Выигрыш
Выплата
Гарантии
Гарантированный
Гарантия возврата денег
Дебет
кредит
Действуй сейчас
Деньги
Дешевая ипотека
Дешево
Диагностика
Долг
Доллар
Дом
Домашний бизнес
Домен
Дополнительный доход
Дорогой
друг
Доступ
Доступный
Доход
Друг
Заказ
заказать
Запусти свой бизнес
Зарабатывай $
Зарабатывай дома
Заработай
Заработок
Зарегистрируйтесь
Зарегистрируйтесь сейчас
Зачем платить больше
Здесь
Знаменитость
Избавьтесь
Излечени
Инструкции
Информация по вашему запросу
Ипотека
Казино
Карта
Коллекторы
Коммерционное предложение
Коммерция
Конкурс
Конфиденциально
Копия
Кредит
Кредитное бюро
Кредитные карты
Кредиторы
Купить напрямую
Купить
Лекарства
Лучшее предложение
Маркетинг
Миллион долларов
Миллион
Миллионер
Минимальный платеж
Множество
Нажми здесь
Нажми сюда
Название бренда
Наличные
Начни сейчас
Не медли
Не проходите мимо
Не спам
Не удаляйте
Неограниченные возможности
Неограниченный доступ
Новинка
Новый
Номер один
Образец
Откройте
Перейдите по ссылке
Персональная скидка
Пищевые добавки
Победа
победитель
Подписка
Подпишитесь
Позвоните
Поздравляем
Покупка
Получатель
Получи миллион
Получи сейчас
Порно
Посетите
наш сайт
Последний шанс
Потрясающий
Похудение
Прайс
Предложение ограниченно
Преимущество
Привет
Приз (подарок)
Продажи
Процентная ставка
Прочитайте
Работай из дома
Распечатайте
Рассылка
Результат
Реклама
Рефинансирование
Решение
Розыгрыш
Рынок
Сам себе начальник
Самая
низкая цена
Самые низкие цены
Сбережения
Сбросьте вес
Свобода
Сделай это сейчас
Сделка
Секрет успеха
Секс
Сертифицированный
Скидка
Скрытые комиссии
Скрытый
Сохраните
Специальная скидка
Специально для вас
Специальное предложение
Специальное средство
Сравните
Средний
Срок
Ставки на спорт
Ставки
Статус заказа
Сто процентов
Сто процентов бесплатно
Стоп
Телефон
Тест
Только сейчас
Транзакции
Тысяча
Увеличь трафик
Удовлетворение
Удовольствие 100%
Условия
Успех
Фантастический
Форма
Фриланс
Храп
Чудо
Шанс
Эксклюзив
OOPSPAM_SPAM_WORDS_RU;

    return array(
        'en' => array(
            'label' => 'English',
            'words' => $oopspam_words_en,
        ),
        'zh' => array(
            'label' => 'Chinese',
            'words' => $oopspam_words_zh,
        ),
        'ru' => array(
            'label' => 'Russian',
            'words' => $oopspam_words_ru,
        ),
    );
}
