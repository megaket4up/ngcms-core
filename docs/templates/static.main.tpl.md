Шаблон static/default.main.tpl
------------------------------

Данный шаблон может подменять шаблон mail.tpl сайта.
Администратор сайта при редактировании статической страницы может активировать опцию "Использовать main.tpl из шаблона".
Данная опция позволяет для конкретной статической страницы использовать свой собственный main.tpl, правила формирования имени:
для шаблона static/NAME.tpl должен создаваться шаблон static/NAME.main.tpl

Набор переменных и блоков одинаков с шаблоном main.tpl.


При использовании собственного main.tpl не забываем про переменную {mainblock}

Пример заполнения шаблона
-------------------------

<pre >
&lt;html>
&lt;head>
&lt;title>{title}&lt;/title>
&lt;/head>
&lt;body>
{mainblock}
&lt;/body>
&lt;/html>
</pre>
