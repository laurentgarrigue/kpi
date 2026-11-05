<?php

namespace App\AgeCategory;

/** Supplies the category table to AgeCategoryRule (kp_categorie in production, a fixed table in tests). */
interface AgeCategoryTable
{
    /** @return list<AgeCategory> */
    public function all(): array;
}
