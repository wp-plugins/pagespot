=== PageSpot ===
Contributors: nickpdx
Donate link:
Tags: page, cms, sidebar
Requires at least: 2.7
Tested up to: 2.9.1
Stable tag: 0.1.4

PageSpot facilitates more complex layout options for Pages and Posts, and 
dynamically assignable sidebar content on a per-Page and per-Post basis.

== Description ==

PageSpot uses private Pages as groups of content to be inserted in various
other places in your Page, Post, or theme. By default it creates two special
private Pages:

*PageSpot Snippets* are pieces of another Page or Post.  To create a new snippet,
just create a child Page of the [PageSpot] Page Snippets page.  PageSpot will
ensure your new snippet remains Private and inaccessible on its own.
PageSpot then expects you to assign the snippet to a Spot on one of your
public Pages.

To do that, you will first need to create custom templates in your theme
directory.  If you create a template with "pagespot" in the name, and select
it in the editor, the PageSpot admin box will become active for that
Page or Post.  PageSpot will parse the template for annotations that look like
"[[PageSpot:Foo]]", and allow you to assign a PageSpot Snippet to the
"Foo" spot.

The final result is that when viewed from the front end, your template will have
the assigned snippet Page's content inserted in place of your [[PageSpot]]
annotation.  This lets you build up complex layouts composed of several
Pages, content which you can edit and replace independent of each
other.

*Sidebar Items* are pages that together comprise a sidebar for your theme. 
To create a sidebar, just create a private Page named "[Sidebar] Foo".  All
child pages of that page constitute the Foo sidebar.  PageSpot will add a
meta-box in the Page Edit screen to select the sidebar to use for the
current Page or Post.  Adding some simple PHP code to your sidebar template is 
all you then need to bring this functionality into your custom theme:

`<?php PageSpot::print_sidebar(); ?>`

== Installation ==

1. Extract PageSpot archive to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress

* Creating a PageSpot page template*
1. Create a template in your theme directory with "pagespot" in the filename (See screenshot 1)
1. Add spot annotations to your template of the form [[PageSpot:aSpotName]]
1. Create a child Page of the private Page "[PageSpot] Page Snippets" (See screenshot 2)
1. Edit the target public Page, in the Template dropdown select "pagespot-foo" (See screenshot 3)
1. In the PageSpot meta box, the names of your template annotations are displayed; assign a Page to each.

* Creating a PageSpot sidebar*
1. Add child pages to the private Page "[Sidebar] Default Sidebar"
1. Add the sidebar code to your theme template: `<?php PageSpot::print_sidebar(); ?>`
1. Assign the sidebar to your public Pages using the Sidebar meta box
1. Create other sidebars by creating private Pages named "[Sidebar] Your Name Here"

== Frequently Asked Questions ==

== Screenshots ==
1. An example PageSpot template, pagespot-nsew.php, with spots for North, South, East, and West
2. Adding PageSpot snippets to be reused in public Page layouts
3. After selecting a PageSpot template, you can assign pages to fill in the spots in that template
4. Creating selectable Sidebars by adding child pages to private "[Sidebar] Foo" pages
5. Assigning a sidebar to a Page