<?php

function smarty_modifier_cfg($key, $instance = \C::DEFAULT_CONFIG_ROOT) {

    return \C::get($key, $instance);
}

// EIF