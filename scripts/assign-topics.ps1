# Скрипт за случайно разпределение на теми за курсова задача
# Използване: .\assign-topics.ps1
# По желание можете да подадете имената като параметър: .\assign-topics.ps1 -Students "Иван","Мария",...

param(
    [string[]]$Students = @(
        "Студент 1",
        "Студент 2",
        "Студент 3",
        "Студент 4",
        "Студент 5",
        "Студент 6",
        "Студент 7",
        "Студент 8",
        "Студент 9",
        "Студент 10",
        "Студент 11"
    )
)

$topics = @(
    [PSCustomObject]@{ Nr = 0;  Name = "Любима музика" },
    [PSCustomObject]@{ Nr = 1;  Name = "Любими храни" },
    [PSCustomObject]@{ Nr = 2;  Name = "Географски справочник" },
    [PSCustomObject]@{ Nr = 3;  Name = "Спортен справочник" },
    [PSCustomObject]@{ Nr = 4;  Name = "Любими филми" },
    [PSCustomObject]@{ Nr = 5;  Name = "Компютърна техника" },
    [PSCustomObject]@{ Nr = 6;  Name = "Медицински справочник" },
    [PSCustomObject]@{ Nr = 7;  Name = "Любими заведения" },
    [PSCustomObject]@{ Nr = 8;  Name = "Студентски справочник" },
    [PSCustomObject]@{ Nr = 9;  Name = "Телефонна книга" },
    [PSCustomObject]@{ Nr = 10; Name = "Каталог научни статии" }
)

if ($Students.Count -ne $topics.Count) {
    Write-Error "Броят на студентите ($($Students.Count)) трябва да е равен на броя на темите ($($topics.Count))."
    exit 1
}

# Get-Random с -Count равен на броя елементи връща всички елементи 
# в произволен ред (Fisher-Yates разбъркване)
$shuffledTopics = $topics | Get-Random -Count $topics.Count

$result = for ($i = 0; $i -lt $Students.Count; $i++) {
    [PSCustomObject]@{
        Студент = $Students[$i]
        "№"     = $shuffledTopics[$i].Nr
        Тема    = $shuffledTopics[$i].Name
    }
}

$result | Format-Table -AutoSize
