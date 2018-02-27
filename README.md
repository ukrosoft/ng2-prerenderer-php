# Angular 2+ templates PHP prerenderer

This library can be used for prerendering your Angular 2+ templates in any PHP based backend

## Basic usage

First of all you should initialize the prerender class and specify the path to the root of your Angular app sources directory

```PHP
$prerenderer = new NG2Prerenderer('/path/to/Angular-app/root');
```

All components will be detected with the prerenderer automatically and you can render any of them separatly

```PHP
echo $prerenderer->render('app-navbar');
echo $prerenderer->render('app-header');
echo $prerenderer->render('app-homepage');
echo $prerenderer->render('app-footer');
```

"render" function takes second paramater as as associative array of the data, which will be passed to the template

```PHP
echo $prerenderer->render('app-navbar', [
    'menu_items'    => $menu_items,
    'menu_settings  => $menu_settings
]);
```

## Supported features

For now this library supports the mext set of the Angular syntax constructions

1. Template variables, including hierarchical arrays: 

```HTML
<p>{varname}</p> 
<p>{arrayname.subarray.varname}</p>
```

2. Component tags with passing vars from the root component: <app-menu-item [var1]="value1"></app-menu-item>

```HTML
<app-menu-item 
    [url]="item.url"
    [title]="item.title"></app-menu-item> 
```

3. InnerHTML directive

```HTML
<div [innerHtml]="page.content_html"></div>
```

4. Structure directives: *ngIf, *ngFor (partial support)

```HTML
<nav *ngIf="menu_settings.enabled">
    <ul>
        <li *ngFor="let item of menu_items">
            <a href="{{ item.url }}">{{ item.title }}</a>
        </li>    
    </ul>
</nav>
```

5. ngStyle and ngClass directives (partial support)

```HTML
<li [ngClass]="{'active': item.is_active}"></li>
```

```HTML
<div [ngStyle]="{'background-image': 'url(' + image_url + ')'}"></div>
```

6. Replacing routerLink with href attribute


## Roadmap

This is a very simple and raw solution and we planning to implement a lot of additional features to the library:

* Angular pipes support with manual registering PHP-based custom pipes
* Internal caching for parsed templates to speedup prerendering
* Extending of *ngIf conditions support
* Extending of *ngFor parameters and syntax support
* Extending of ngClass and ngStyle directives syntax support  