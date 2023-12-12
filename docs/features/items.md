<!-- docs/features/items.md -->

> 🚧 Under construction

## Adding icon to Item or Folder

For each Item or Folder, it is possible to add an icon permitting to have it in prefix when object is displayed.

### FontAwesome icons

Teampass uses [FontAwesome Icons](https://fontawesome.com/search?o=r&m=free).

In edition mode, 

* set focus in field `Icon`
* enter the icon FA code you want to use
* once leaving the field, the icon is added at the right

![1](../../_media/tp3_items_1.png)

```
# Display an hippo FA icon
fa-solid fa-hippo
```

💡 You also might add some FA attributes such as `fa-xl`, `fa-rotate-90`, ... See [Styling with Font Awesome](https://fontawesome.com/docs/web/style/styling) page.

```
# Display an hippo FA icon with size XL
fa-solid fa-hippo fa-xl
```

### Special 

Icons are handled as text. As a consequence, you might use a theme color for them in order to make them displayed a special way.

Teampass layout has defined 6 themes:

| Name | Code to use in Teampass |
| ---- | ----------------------- |
| <span style="color:#007bff">Primary</span> | `text-primary` |
| <span style="color:#6c757d">Secondary/span> | `text-secondary` |
| <span style="color:#28a745">Success</span> | `text-success` |
| <span style="color:#17a2b8">Info</span> | `text-info` |
| <span style="color:#ffc107">Warning</span> | `text-warning` |
| <span style="color:#dc3545">Danger</span> | `text-danger` |
| <span style="color:#6610f2">Indigo</span> | `text-indigo` |
| <span style="color:#001f3f">Navy</span> | `text-navy` |
| <span style="color:#6f42c1">Purple</span> | `text-purple` |
| <span style="color:#f012be">Fuchsia</span> | `text-fuchsia` |
| <span style="color:#e83e8c">Pink</span> | `text-pink` |
| <span style="color:#d81b60">Maroon</span> | `text-maroon` |
| <span style="color:#fd7e14">Orange</span> | `text-orange` |
| <span style="color:#01ff70">Lime</span> | `text-lime` |
| <span style="color:#20c997">Teal</span> | `text-teal` |
| <span style="color:#3d9970">Olive</span> | `text-olive` |

![1](../../_media/tp3_items_2.png)

```
# Display an hippo FA icon with size XL and red
fa-solid fa-hippo fa-xl text-danger
```

In the items list, the icon is prefixed to the item label.

![1](../../_media/tp3_items_3.png)

## One Time View

> OTV permits to share an item to any one that doesn't have access to Teampass instance.

Once enabled by the Administrator, this feature allows to create a link dedicated for an item.
It will be valid a date (by default 7 days) and for a certain number of views (by default 1 time).

An option permits also to define a subdomain.
Let's considere that your Teampass domain is only visible by your organization.
It is possible to define a subdomain open to everyone.
If an Administrator defines a subdomain, any link generated will contain this subdomain and will be available for any user in and outside your organization.

If any valid OTV link exists for an item, a special Icon will be displayed with the number of links.

![1](../../_media/tp3_otv_1.png)

## OTP code for Item

> Teampass can display an OTP code for each item.

### Show the OTP code

When viewing an item, you have access to rotating OTP code.

![1](../../_media/tp3_otp_1.png)

### Setting up the OTP code for an item

In some circumstancies, it can be usefull to have set up an OTP code for any critical entry.
Teampass permits you to store the OTP setup instead of using a dedicated OTP tool.
By doing so, you can share the OTP code among all users.

Setting up an OTP code for one item is performed in the edition form.

* Select `Details` tab
* Fill in `Phone number` field; it is optional but could be usefull to recover an access
* Fill in `Secret key` field; this one comes from the website for which you have decided to set up an OTP. In general, you need to scan a QR code but the secret is also provided
* Finally, you can decide to enable or not showing the OTP code

![1](../../_media/tp3_otp_2.png)