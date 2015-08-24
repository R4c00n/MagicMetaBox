# MagicMetaBox
Helper class to generate wordpress metaboxes

**Work in progress**

## Installation
If you're using composer, run:
```
composer require gloeckchen/magic-meta-box:*
```
Require composer `autoload.php` and add `use MagicMetaBox\MagicMetaBox;` to your plugins code.

## Usage

### Create a metabox
Create new instance of MagicMetaBox
```
$myMetaBox = new MagicMetaBox(
    'my-metabox', // unique metabox id
    'My MetaBox title', // Metabox title
    array( 'post', 'page' ), // Metabox screens
    'prefix_' // Meta prefix,
    'side', // Metabox context (optional)
    'high' // Mitabox priority (optional)
); 
```

### Add input fields
Add a text input field to your new metabox:
```
$myMetaBox->addTextField( 
    'my-text-field', // Input name
    array( // Additional attributes
        'class' => 'input-field'
    ),
    'My text field', // Associated label text
    'I am a default', // Default value
);
```

Add a textarea to your new metabox:
```
$myMetaBox->addTextAreaField( 
    'my-text-arae', // Textarea name
    array(), // Additional attributes
    'My text area', // Associated label text
    'I am a default', // Default value
);
```

Add a checkbox to your new metabox:
```
$myMetaBox->addCheckboxField( 
    'my-checkbox', // Checkbox name
    array(), // Additional attributes
    'My checkbox' // Associated label text
);
```

Add a select input to your new metabox:
```
$myMetaBox->addSelectField( 
    'my-select', // Select name
    array( // Options
      0 => 'Yes',
      1 => 'No'
    ),
    true, // Multiple
    array( // Additional attributes
        'size' => 5
    ), 
    'My select' // Associated label text
);
```

### Access meta data
Access meta data via prefix + metabox id. Data is stored as array with input names as keys.

```
$meta = get_post_meta( $postId, 'prefix_my-metabox', true );
echo $meta['my-text-field'];
```